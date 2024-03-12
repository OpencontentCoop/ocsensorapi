<?php

namespace Opencontent\Sensor\Inefficiency;

use GuzzleHttp\Exception\RequestException;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Message\Comment;
use Opencontent\Sensor\Api\Values\Message\Response;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Stanzadelcittadino\Client\Credential;
use Opencontent\Stanzadelcittadino\Client\Exceptions\MessageNotFound;
use Opencontent\Stanzadelcittadino\Client\Exceptions\UserByFiscalCodeNotFound;
use Opencontent\Stanzadelcittadino\Client\Exceptions\UserGroupByNameNotFound;
use Opencontent\Stanzadelcittadino\Client\Request\AcceptApplication;
use Opencontent\Stanzadelcittadino\Client\Request\AssignApplication;
use Opencontent\Stanzadelcittadino\Client\Request\CreateApplication;
use Opencontent\Stanzadelcittadino\Client\Request\CreateApplicationMessage;
use Opencontent\Stanzadelcittadino\Client\Request\CreateUser;
use Opencontent\Stanzadelcittadino\Client\Request\CreateUserGroupWithName;
use Opencontent\Stanzadelcittadino\Client\Request\GetApplicationByUuid;
use Opencontent\Stanzadelcittadino\Client\Request\GetApplicationMessageByText;
use Opencontent\Stanzadelcittadino\Client\Request\GetUserByFiscalCode;
use Opencontent\Stanzadelcittadino\Client\Request\GetUserByUuid;
use Opencontent\Stanzadelcittadino\Client\Request\GetUserGroupByName;
use Opencontent\Stanzadelcittadino\Client\RequestHandlerInterface;
use Psr\Http\Client\ClientExceptionInterface;
use eZPendingActions;
use eZContentObject;
use eZContentFunctions;

class Listener extends AbstractListener
{
    private $repository;

    private $postSerializer;

    private $userSerializer;

    private $binarySerializer;

    private $messageSerializer;

    private $client;

    /**
     * @var Post
     */
    private $post;

    private $remoteIdentifier;

    private $isRetryContext = false;

    private $defaultUserGroupName;
    
    private $serviceIdentifier;

    public function __construct(\OpenPaSensorRepository $repository)
    {
        $this->repository = $repository;
        $this->postSerializer = new PostSerializer();
        $this->userSerializer = new UserSerializer();
        $this->binarySerializer = new BinarySerializer();
        $this->messageSerializer = new MessageSerializer();
        $this->client = $this->repository->getInefficiencyClient();
        $this->defaultUserGroupName = $this->repository->getSensorSettings()->get('Inefficiency')->default_group_name;
        $this->serviceIdentifier = $this->repository->getSensorSettings()->get('Inefficiency')->service_identifier;
    }

    public function setIsRetryContext(): Listener
    {
        $this->isRetryContext = true;
        return $this;
    }

    private function request(RequestHandlerInterface $requestHandler, ?string $as = null)
    {
        return ($this->client)($requestHandler, $as);
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            $this->handleSensorEvent($param);
        }
    }

    protected function handleSensorEvent($sensorEvent)
    {
        try {
            $this->post = $sensorEvent->post;
            $this->remoteIdentifier = $this->post->meta['application']['id']
                ?? $this->post->meta['payload']['id']
                ?? null;
            switch ($sensorEvent->identifier) {
                case 'on_create':
                    $this->onCreate();
                    break;

                case 'on_approver_first_read':
                case 'on_fix':
                    $this->onApproverFirstRead();
                    break;

                case 'on_group_assign':
                case 'on_assign':
                    $this->onAssign();
                    break;

                case 'on_create_comment':
                    $this->onCreateComment($sensorEvent->parameters['message']);
                    break;

                case 'on_add_response':
                    $this->onAddResponse();
                    break;

                case 'on_close':
                    $this->onClose();
                    break;

                case 'on_add_attachment':
                case 'on_remove_attachment':
                case 'on_edit_comment':
                case 'on_edit_response':
                    //@todo
                    break;
            }
        } catch (\Throwable $e) {
            $this->repository->getLogger()->error($e->getMessage(), ['event' => $sensorEvent->identifier]);
            if ($e instanceof RequestException && $e->getResponse()->getStatusCode() >= 500) {
                $this->addToRetryQueue($sensorEvent);
            }
        }
    }

    private function addToRetryQueue(SensorEvent $event)
    {
        $now = time();
        $action = new eZPendingActions([
            'action' => 'inefficiency_retry',
            'created' => time(),
            'param' => serialize($event),
        ]);
        $action->store();
    }

    private function onCreate()
    {
        if ($this->remoteIdentifier !== null) {
            return;
        }
        $author = $this->post->author;
        if (empty($author->fiscalCode)) {
            throw new \RuntimeException('Missing fiscalCode in user %s', $author->id);
        }
        $userStruct = $this->userSerializer->serialize($author);
        try {
            $user = $this->request(new GetUserByFiscalCode($userStruct->codice_fiscale));
        } catch (UserByFiscalCodeNotFound $e) {
            $user = $this->request(new CreateUser($userStruct));
        }

        $images = [];
        $docs = [];
        foreach ($this->post->images as $image) {
            $images[] = $this->uploadBinary($image);
        }
        foreach ($this->post->files as $file) {
            $docs[] = $this->uploadBinary($file);
        }

        $application = $this->request(
            new CreateApplication(
                $this->postSerializer->serialize(
                    $this->post,
                    $userStruct,
                    $user['id'],
                    $images,
                    $docs,
                    $this->serviceIdentifier
                )
            )
        );
        $this->remoteIdentifier = $application['id'] ?? null;
        if ($this->remoteIdentifier) {
            $meta = $this->post->meta;
            $meta['application'] = $application;
            $meta['pingback_url'] = $this->client->getApiUri() . '/applications/' . $this->remoteIdentifier;
            $contentObject = eZContentObject::fetch($this->post->id);
            if ($contentObject instanceof eZContentObject) {
                $contentObject->setAttribute('remote_id', $this->remoteIdentifier);
                $dataMap = $contentObject->dataMap();
                if (isset($dataMap['meta'])) {
                    $dataMap['meta']->fromString(json_encode($meta));
                    $dataMap['meta']->store();
                }
            }
            $this->onApproverFirstRead();
        }
    }

    /**
     * @param Post\Field\Image|Post\Field\File $file
     * @return array
     * @throws \Opencontent\Stanzadelcittadino\Client\Exceptions\FailBinaryCreate
     */
    private function uploadBinary(Post\Field $file)
    {
        $struct = $this->binarySerializer->serialize($file);
        return $this->client->uploadBinary(
            $struct['path'],
            $struct['original_filename'],
            $struct['mime_type']
        );
    }

    private function onApproverFirstRead()
    {
        if ($this->remoteIdentifier === null) {
            return;
        }

        try {
            $userGroup = $this->request(new GetUserGroupByName($this->defaultUserGroupName));
        } catch (UserGroupByNameNotFound $e) {
            $userGroup = $this->request(new CreateUserGroupWithName($this->defaultUserGroupName));
        }

        $userProperties = $this->client->getCredential(Credential::OPERATOR, true)->getProperties();
        $userUuid = $userProperties['id'] ?? null;
        $this->request(
            new AssignApplication($this->remoteIdentifier, $userGroup['id'], $userUuid),
            Credential::OPERATOR
        );
    }

    private function onAssign()
    {
        if ($this->remoteIdentifier === null) {
            return;
        }
        /** @var Participant $ownerGroup */
        $ownerGroup = $this->post->owners->getParticipantsByType(Participant::TYPE_GROUP)->first();
        if ($ownerGroup) {
            try {
                $userGroup = $this->request(new GetUserGroupByName($ownerGroup->name));
            } catch (UserGroupByNameNotFound $e) {
                $userGroup = $this->request(new CreateUserGroupWithName($ownerGroup->name));
            }
            $this->request(new AssignApplication($this->remoteIdentifier, $userGroup['id']), Credential::OPERATOR);
        } else {
            $this->repository->getLogger()->warning('Group not found', ['method' => __METHOD__]);
        }
    }

    private function onCreateComment(Comment $comment)
    {
        if ($this->remoteIdentifier === null) {
            return;
        }
        if ($comment->creator->id === $this->post->author->id){
            return;
        }
        
        $messageStruct = $this->messageSerializer->serialize($this->post, $comment);
        try {
            $this->request(new GetApplicationMessageByText($messageStruct->message, $this->remoteIdentifier));
            $this->repository->getLogger()->debug('Message already pushed');
        } catch (MessageNotFound $e) {
            $this->request(new CreateApplicationMessage($this->remoteIdentifier, $messageStruct));
        }
    }

    private function onAddResponse()
    {
        if ($this->remoteIdentifier === null) {
            return;
        }
        $messageStruct = $this->messageSerializer->serialize($this->post, $this->post->responses->last());
        try {
            $this->request(new GetApplicationMessageByText($messageStruct->message, $this->remoteIdentifier));
            $this->repository->getLogger()->debug('Message already pushed');
        } catch (MessageNotFound $e) {
            $this->request(new CreateApplicationMessage($this->remoteIdentifier, $messageStruct));
        }
    }

    private function onClose()
    {
        if ($this->remoteIdentifier === null) {
            return;
        }
        $application = $this->request(new GetApplicationByUuid($this->remoteIdentifier));
        if ((int)$application['status'] >= 7000) {
            $this->repository->getLogger()->debug("Application $this->remoteIdentifier already accepted");
            return $application;
        }
        $lastResponse = $this->post->responses->last();
        $this->request(
            new AcceptApplication($this->remoteIdentifier, $lastResponse ? $lastResponse->text : null),
            Credential::OPERATOR
        );
    }
}