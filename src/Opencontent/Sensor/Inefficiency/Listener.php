<?php

namespace Opencontent\Sensor\Inefficiency;

use eZSiteAccess;
use GuzzleHttp\Exception\RequestException;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Participant;
use eZPendingActions;
use OpenPaSensorRepository;
use SQLIImportUtils;
use Throwable;

class Listener extends AbstractListener
{
    const PENDING_RETRY_ACTION = 'inefficiency_retry';

    private $repository;

    private $events = [
        'on_create',
        'on_approver_first_read',
        'on_fix',
        'on_group_assign',
        'on_assign',
        'on_create_comment',
        'on_add_response',
        'on_close',
    ];

    public function __construct(OpenPaSensorRepository $repository)
    {
        $this->repository = $repository;
    }


    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            if (
                $this->repository->getSensorSettings()->get('Inefficiency')->is_enabled
                && in_array($param->identifier, $this->events)
            ) {
                $remoteIdentifier = $param->post->meta['application']['id']
                    ?? $param->post->meta['payload']['id']
                    ?? null;

                $isBehalf = $param->post->author->id !== $param->post->reporter->id && $param->post->reporter->behalfOfMode;

                if (($param->identifier === 'on_create' && !$remoteIdentifier && $isBehalf) || $remoteIdentifier !== null) {
                    $this->repository->getLogger()->info(
                        sprintf('Enqueue %s post %s to inefficiency', $param->identifier, $param->post->id)
                    );
                    $this->addToQueue($param);
                    $this->runCommand();
                } else {
                    $this->repository->getLogger()->info(
                        sprintf('Push %s post %s not necessary', $param->identifier, $param->post->id)
                    );
                }
            }
        }
    }

    /**
     * @see InefficiencyRetryHandler::process()
     */
    public function handleSensorEvent(SensorEvent $sensorEvent): ?string
    {
        $post = $sensorEvent->post;
        $handler = new PostHandler($post);
        $remoteIdentifier = $post->meta['application']['id']
            ?? $post->meta['payload']['id']
            ?? null;

        try {
            switch ($sensorEvent->identifier) {
                case 'on_create':
                    if ($remoteIdentifier === null) {
                        $handler->assertExistApplication();
                        $handler->assignToDefaultGroup();
                        $remoteIdentifier = $handler->getApplicationId();
                    }
                    break;

                case 'on_approver_first_read':
                case 'on_fix':
                    if ($remoteIdentifier !== null) {
                        $handler->assignToDefaultGroup();
                    }
                    break;

                case 'on_group_assign':
                case 'on_assign':
                    if ($remoteIdentifier !== null) {
                        /** @var Participant $ownerGroup */
                        $ownerGroup = $post->owners->getParticipantsByType(Participant::TYPE_GROUP)->first();
                        if ($ownerGroup) {
                            $handler->assignToGroup($ownerGroup->name);
                        } else {
                            $this->repository->getLogger()->warning('Group not found', ['method' => __METHOD__]);
                        }
                    }
                    break;

                case 'on_create_comment':
                    if ($remoteIdentifier !== null) {
                        $handler->addMessage($sensorEvent->parameters['message']);
                    }
                    break;

                case 'on_add_response':
                    if ($remoteIdentifier !== null) {
                        $handler->addMessage($post->responses->last());
                    }
                    break;

                case 'on_close':
                    if ($remoteIdentifier !== null) {
                        $handler->accept();
                    }
                    break;

                case 'on_add_attachment':
                case 'on_remove_attachment':
                case 'on_edit_comment':
                case 'on_edit_response':
                    //@todo
                    break;
            }

            return "$sensorEvent->identifier $post->id $remoteIdentifier";
        } catch (Throwable $e) {
            $this->repository->getLogger()->error($e->getMessage(), ['event' => $sensorEvent->identifier]);
            if ($e instanceof RequestException && $e->getResponse()->getStatusCode() >= 500) {
                $this->addToQueue($sensorEvent);
            }
            return "[ERROR] " . $e->getMessage() . ' ' . $remoteIdentifier;
        }
    }

    private function runCommand()
    {
        $count = eZPendingActions::count(eZPendingActions::definition(), [
            'action' => Listener::PENDING_RETRY_ACTION,
        ]);
        if ($count > 0) {
            $command = 'php extension/sqliimport/bin/php/sqlidoimport.php -q -s'
                . eZSiteAccess::current()['name']
                . ' --source-handlers=inefficiency_retry > /dev/null &';
            $this->repository->getLogger()->info('Run command ' . $command);
            exec($command);
        }
    }

    private function addToQueue(SensorEvent $event)
    {
        $this->repository->getLogger()->debug('Add event to queue ' . $event->identifier);
        $action = new eZPendingActions([
            'action' => self::PENDING_RETRY_ACTION,
            'created' => time(),
            'param' => SQLIImportUtils::safeSerialize($event),
        ]);
        $action->store();
    }

}