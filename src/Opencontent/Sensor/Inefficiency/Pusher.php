<?php

namespace Opencontent\Sensor\Inefficiency;

use DateTime;
use ezcConsoleOutput;
use ezcConsoleProgressbar;
use eZCLI;
use eZPendingActions;
use eZPersistentObject;
use Opencontent\Sensor\Api\Values\Message\TimelineItem;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use eZContentObject;
use Opencontent\Stanzadelcittadino\Client\Credential;
use Opencontent\Stanzadelcittadino\Client\Exceptions\FailBinaryCreate;
use Opencontent\Stanzadelcittadino\Client\HttpClient;
use OpenPaSensorRepository;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Throwable;

class Pusher extends AbstractLogger
{
    private $options;

    private $repository;

    private $slack;

    /**
     * @var Post
     */
    private $current;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var string
     */
    private $group;

    public function __construct(array $options = [])
    {
        $options['id'] = $options['id'] ?? null;
        $this->slack = $options['slack-endpoint'] ?? null;
        $this->options = $options;
        $this->repository = OpenPaSensorRepository::instance();
        $this->operator = getenv('MIGRATION_OPERATOR_NAME') ?? 'Sensor Team';
        $this->group = getenv('MIGRATION_GROUP_NAME') ?? 'Redazione SensorCivico';
    }

    private function instanceClient(\stdClass $clientSettings): HttpClient
    {
        $clientSettings->severity_map = (array)$clientSettings->severity_map;
        $client = (new HttpClient($clientSettings->base_url))->addCredential(
            Credential::API_USER,
            $clientSettings->api_login,
            $clientSettings->api_password
        )->addCredential(
            Credential::ADMIN,
            $clientSettings->admin_login ?? '',
            $clientSettings->admin_password ?? ''
        );
        $client->setLogger($this);

        return $client;
    }

    public function run($callable = null)
    {
        $objects = $this->fetch();
        $objectsCount = count($objects);
        if (!$this->options['verbose']) {
            $output = new ezcConsoleOutput();
            $progressBarOptions = ['emptyChar' => ' ', 'barChar' => '='];
            $progressBar = new ezcConsoleProgressbar($output, $objectsCount, $progressBarOptions);
            $progressBar->start();
        }

        $clientSettings = (object)[
            'is_enabled' => true,
            'tenants' => ['*'],
            'api_login' => getenv('MIGRATION_API_LOGIN'),
            'api_password' => getenv('MIGRATION_API_PASSWORD'),
            'admin_login' => getenv('MIGRATION_ADMIN_LOGIN'),
            'admin_password' => getenv('MIGRATION_ADMIN_PASSWORD'),
            'base_url' => getenv('MIGRATION_BASE_URL'),
            'default_group_name' => 'Ufficio relazioni con il pubblico',
            'service_identifier' => 'inefficiencies',
            'service_slug' => 'segnalazione-disservizio',
            'severity_map' => [
                '1' => 'suggerimento',
                '2' => 'suggerimento',
                '3' => 'segnalazione',
                '4' => 'segnalazione',
                '5' => 'reclamo',
            ],
        ];
        if ($this->options['show-config']){
            print_r($clientSettings);
        }

        foreach ($objects as $object) {
            try {
                $this->current = $this->repository->getPostService()->loadPost((int)$object['id']);
                if (!$this->options['dry-run']) {
                    if (is_callable($callable)) {
                        call_user_func($callable, $this->current, $clientSettings);
                    } else {
                        $this->push($this->current, $clientSettings);
                    }
                } else {
                    if ($this->options['verbose']) {
                        $this->info($this->current->id);
                    }
                }
            } catch (Throwable $e) {
                $this->notify('Error on post ' . $object['id'] . ': ' . $e->getMessage());
                $this->error($e->getMessage());
                continue;
            }
            if (!$this->options['verbose']) {
                $progressBar->advance();
            }
        }

        if (!$this->options['verbose']) {
            $progressBar->finish();
        }
    }

    public function log($level, $message, array $context = [])
    {
        if ($this->current) {
            $message = '[' . $this->current->id . '][' . $level . '] ' . $message;
        }
        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
                eZCLI::instance()->error($message);
                break;

            case LogLevel::WARNING:
                eZCLI::instance()->warning($message);
                break;

            case LogLevel::NOTICE:
                if ($this->options['verbose']) {
                    eZCLI::instance()->notice($message);
                }
                break;

            default:
                if ($this->options['verbose']) {
                    eZCLI::instance()->output($message);
                }
        }
    }

    private function fetch(): array
    {
        if ($this->options['id']) {
            $objects = [eZContentObject::fetch((int)$this->options['id'], false)];
        } else {
            $closeStateIdList = $openStateIdList = [];
            $states = $this->repository->getSensorPostStates('sensor');
            foreach ($states as $state) {
                if ($state->attribute('identifier') === 'close') {
                    $closeStateIdList[] = $state->attribute('id');
                } else {
                    $openStateIdList[] = $state->attribute('id');
                }
            }
//            $filterStateIdList = $this->options['only-closed'] ? $closeStateIdList : $openStateIdList;

            $limit = (int)$this->options['limit'];
            $offset = (int)$this->options['offset'];
            $limits = $limit > 0 ? ['limit' => $limit, 'offset' => $offset] : null;

            $conditions = [
                'contentclass_id' => $this->repository->getPostContentClass()->attribute('id'),
                'status' => eZContentObject::STATUS_PUBLISHED,
            ];

            if (!$this->options['only-closed']) {
                $objects = eZPersistentObject::fetchObjectList(
                    eZContentObject::definition(),
                    ['id', 'published'],
                    $conditions,
                    ['published' => 'asc'],
                    $limits,
                    null
                );
            } else {
                $objects = eZPersistentObject::fetchObjectList(
                    eZContentObject::definition(),
                    ['id', 'published'],
                    $conditions,
                    ['published' => 'asc'],
                    $limits,
                    null,
                    false,
                    ['contentobject_state_id'],
                    ['ezcobj_state_link'],
                    ' AND ezcobj_state_link.contentobject_id = ezcontentobject.id and ezcobj_state_link.contentobject_state_id in ('
                    . implode(',', $closeStateIdList) . ')'
                );
            }
        }

        return $objects;
    }

    private function notify($message)
    {
        if ($this->slack) {
            $ch = curl_init($this->slack);
            $data = json_encode([
                "text" => $message,
            ]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    /**
     * @throws FailBinaryCreate
     * @throws Throwable
     */
    private function push(Post $post, $clientSettings)
    {
        if ($this->options['force']){
            unset($post->meta['application']['id']);
        }

        if (isset($post->meta['application']['id'])) {
            $this->storeStatusChange($post, $post->meta['application']['id']);
            return;
        }

        $handler = new PostHandler($post, $this->instanceClient($clientSettings), $this);
        $handler->setPostSerializer(new PusherPostSerializer($clientSettings->severity_map));
        if ($this->options['env'] && $this->options['env'] === 'dev') {
            $handler->setUserSerializer(new PusherUserSerializer());
        }

        $handler->assertExistApplication();

        /** @var TimelineItem $timelineItem */
        foreach ($post->timelineItems as $timelineItem) {
            if ($timelineItem->type === 'read') {
                $handler->assignToDefaultGroup($timelineItem->published);
            } elseif ($timelineItem->type === 'closed') {
                $handler->accept($timelineItem->published);
            }
        }

        foreach ($post->comments as $message) {
            $handler->addMessage($message);
        }

        foreach ($post->responses as $message) {
            $handler->addMessage($message);
        }

        $this->storeStatusChange($post, $handler->getApplicationId());
    }

    private function storeStatusChange(Post $post, string $applicationId)
    {
        $this->debug(sprintf('Store change status for post %s, application %s', $post->id, $applicationId));
        $statusChangeTpl = [
            'evento' => null,
            'operatore' => null,
            'user_group' => null,
            'responsabile' => null,
            'struttura' => null,
            'timestamp' => null,
            'message' => null,
            'message_id' => null,
        ];

        $changes = [];
        $publishedAt = $post->published->format('U');
        $changes[$publishedAt] = [
            [
                2000,
                array_merge($statusChangeTpl, [
                    'evento' => 'Creazione pratica da altro soggetto',
                    'operatore' => $this->operator,
                    'timestamp' => $publishedAt,
                ]),
            ],
        ];

        $read = $post->timelineItems->getByType('read')->first();
        if ($read && $read->published instanceof DateTime) {
            $readAt = $read->published->format('U');
            $changes[$readAt] = [
                [
                    4000,
                    array_merge($statusChangeTpl, [
                        'evento' => 'Presa in carico',
                        'operatore' => $this->operator,
                        'user_group' => $this->group,
                        'timestamp' => $readAt,
                        'responsabile' => $this->operator,
                    ]),
                ],
            ];
        }

        $assignedList = $post->timelineItems->getByType('assigned');
        $lastAssigned = false;
        foreach ($assignedList->messages as $message) {
            foreach ($message->extra as $id) {
                $participant = $post->participants->getParticipantById($id);
                if ($participant && $participant->type == Participant::TYPE_GROUP) {
                    $lastAssigned = [
                        'name' => $participant->name,
                        'at' => $message->published->format('U'),
                    ];
                }
            }
        }

        if ($lastAssigned) {
            $changes[$lastAssigned['at']] = [
                [
                    4000,
                    array_merge($statusChangeTpl, [
                        'evento' => 'Presa in carico',
                        'user_group' => $lastAssigned['name'],
                        'timestamp' => $lastAssigned['at'],
                        'responsabile' => $this->operator,
                    ]),
                ],
            ];
        }


        $closed = $post->timelineItems->getByType('closed')->last();
        if ($closed && $closed->published instanceof DateTime) {
            $closedAt = $closed->published->format('U');
            $changes[$closedAt] = [
                [
                    7000,
                    array_merge($statusChangeTpl, [
                        'evento' => 'Approvazione pratica',
                        'operatore' => $this->operator,
                        'user_group' => $this->group,
                        'timestamp' => $closedAt,
                        'responsabile' => $this->operator,
                    ]),
                ],
            ];
        }

        $action = 'inefficiency_staus_change';
        $param = ['id' => $applicationId, 'storico_stati' => serialize($changes)];
        $pendingItem = eZPendingActions::fetchObject(eZPendingActions::definition(), null, [
            'action' => $action,
            'param' => json_encode($param),
        ]);
        if ($pendingItem instanceof eZPendingActions) {
            $pendingItem->setAttribute('created', time());
        } else {
            $rowPending = [
                'action' => $action,
                'created' => time(),
                'param' => json_encode($param),
            ];
            $pendingItem = new eZPendingActions($rowPending);
        }
        $pendingItem->store();

        $connection = getenv('MIGRATION_SDC_DB_CONNECTION');
        if ($connection) {
            $db = new \PDO($connection, getenv('MIGRATION_SDC_DB_USER'), getenv('MIGRATION_SDC_DB_PASSWORD'));
            $sql = "UPDATE pratica SET storico_stati=:storico_stati WHERE id=:id";
            $stmt = $db->prepare($sql);
            $stmt->execute($param);
            $db = null;
        }
    }
}