<?php

namespace Opencontent\Sensor\Inefficiency;

use DateTimeInterface;
use Opencontent\Sensor\Api\Values\Message\Comment;
use Opencontent\Sensor\Api\Values\Message\Response;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Stanzadelcittadino\Client\Credential;
use Opencontent\Stanzadelcittadino\Client\Exceptions\FailBinaryCreate;
use Opencontent\Stanzadelcittadino\Client\Exceptions\MessageNotFound;
use Opencontent\Stanzadelcittadino\Client\Exceptions\UserByFiscalCodeNotFound;
use Opencontent\Stanzadelcittadino\Client\Exceptions\UserGroupByNameNotFound;
use Opencontent\Stanzadelcittadino\Client\HttpClient;
use Opencontent\Stanzadelcittadino\Client\Request\AcceptApplication;
use Opencontent\Stanzadelcittadino\Client\Request\AssignApplication;
use Opencontent\Stanzadelcittadino\Client\Request\CreateApplication;
use Opencontent\Stanzadelcittadino\Client\Request\CreateApplicationMessage;
use Opencontent\Stanzadelcittadino\Client\Request\CreateUser;
use Opencontent\Stanzadelcittadino\Client\Request\CreateUserGroupWithName;
use Opencontent\Stanzadelcittadino\Client\Request\GetApplicationByUuid;
use Opencontent\Stanzadelcittadino\Client\Request\GetApplicationMessageByText;
use Opencontent\Stanzadelcittadino\Client\Request\GetUserByFiscalCode;
use Opencontent\Stanzadelcittadino\Client\Request\GetUserGroupByName;
use Opencontent\Stanzadelcittadino\Client\Request\ReopenApplication;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\User as UserStruct;
use Opencontent\Stanzadelcittadino\Client\RequestHandlerInterface;
use Psr\Log\LoggerAwareTrait;
use eZContentObject;
use OpenPaSensorRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

class PostHandler
{
    use LoggerAwareTrait;

    /**
     * @var Post
     */
    private $post;

    /**
     * @var PostSerializer
     */
    private $postSerializer;

    /**
     * @var BinarySerializer
     */
    private $binarySerializer;

    /**
     * @var UserSerializer
     */
    private $userSerializer;

    /**
     * @var MessageSerializer
     */
    private $messageSerializer;

    /**
     * @var OpenPaSensorRepository
     */
    private $repository;

    private $defaultUserGroupName;

    private $serviceIdentifier;

    /**
     * @var UserStruct
     */
    private $userStruct;

    private $user = null;

    private $application = null;

    /**
     * @var HttpClient
     */
    private $client;

    public function __construct(Post $post, HttpClient $client = null, LoggerInterface $logger = null)
    {
        $this->post = $post;

        $this->repository = OpenPaSensorRepository::instance();
        $this->postSerializer = new PostSerializer(
            $this->repository->getSensorSettings()->get('Inefficiency')->severity_map ?? []
        );
        $this->binarySerializer = new BinarySerializer();
        $this->userSerializer = new UserSerializer();
        $this->messageSerializer = new MessageSerializer();

        $this->logger = $logger ?? new NullLogger();
        $this->client = $client ?? $this->repository->getInefficiencyClient();

        $this->defaultUserGroupName = $this->repository->getSensorSettings()->get('Inefficiency')->default_group_name;
        $this->serviceIdentifier = $this->repository->getSensorSettings()->get('Inefficiency')->service_identifier;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->client->setLogger($this->logger);
    }

    public function getApplicationId()
    {
        return $this->application['id'] ?? null;
    }

    public function setPostSerializer(PostSerializer $postSerializer): void
    {
        $this->postSerializer = $postSerializer;
    }

    public function setUserSerializer(UserSerializer $userSerializer): void
    {
        $this->userSerializer = $userSerializer;
    }

    /**
     * @throws Throwable
     */
    public function assertExistUser()
    {
        $author = $this->post->author;
        if (empty($author->fiscalCode)) {
            throw new RuntimeException(sprintf('Missing fiscalCode in user %s', $author->id));
        }
        $this->logger->debug(sprintf('Find user by fiscal code %s', $author->fiscalCode));
        $this->userStruct = $this->userSerializer->serialize($author);

        try {
            $user = $this->request(new GetUserByFiscalCode($this->userStruct->codice_fiscale));
            $this->logger->info(sprintf('Found user %s', $user['id']));
        } catch (UserByFiscalCodeNotFound $e) {
            $this->logger->debug(sprintf('Create user with fiscal code %s', $author->fiscalCode));
            $user = $this->request(new CreateUser($this->userStruct));
            $this->logger->info(sprintf('New user created %s', $user['id']));
        }
        $this->user = $user;
    }

    /**
     * @throws FailBinaryCreate|Throwable
     */
    public function assertExistApplication()
    {
        $postMetaApplicationId = $this->post->meta['application']['id']
            ?? $this->post->meta['payload']['id']
            ?? $this->application['id']
            ?? null;

        if ($postMetaApplicationId === null) {
            $this->assertExistUser();
            $this->logger->debug(sprintf('Create application from post %s', $this->post->id));
            $images = [];
            $docs = [];
            foreach ($this->post->images as $image) {
                $images[] = $this->uploadBinary($image);
            }
            foreach ($this->post->files as $file) {
                $docs[] = $this->uploadBinary($file);
            }
            $serialized = $this->postSerializer->serialize(
                $this->post,
                $this->userStruct,
                $this->user['id'],
                $images,
                $docs,
                $this->serviceIdentifier
            );

            $this->application = $this->request(
                new CreateApplication(
                    $serialized
                )
            );
            $this->logger->info(sprintf('New application created %s', $this->application['id']));
            $meta = $this->post->meta;
            $meta['application'] = $this->application;
            $meta['pingback_url'] = $this->client->getApiUri() . '/Applications/' . $this->application['id'];
            eZContentObject::clearCache();
            $contentObject = eZContentObject::fetch($this->post->id);
            if ($contentObject instanceof eZContentObject) {
                $contentObject->setAttribute('remote_id', $this->application['id']);
                $contentObject->store();
                $dataMap = $contentObject->dataMap();
                if (isset($dataMap['meta'])) {
                    $dataMap['meta']->fromString(json_encode($meta));
                    $dataMap['meta']->store();
                }
            }
        } else {
            $this->application = $this->request(new GetApplicationByUuid($postMetaApplicationId));
            $this->logger->info(sprintf('Application already exists %s', $this->application['id']));
        }
    }

    /**
     * @throws FailBinaryCreate|Throwable
     */
    public function assignToDefaultGroup(?DateTimeInterface $assignedAt = null)
    {
        $this->logger->debug(sprintf('Assign application %s to default group', $this->application['id']));
        $userProperties = $this->client->getCredential(Credential::API_USER, true)->getProperties();
        $userUuid = $userProperties['id'] ?? null;
        $this->assign($this->defaultUserGroupName, $userUuid, null, $assignedAt);
    }

    /**
     * @throws FailBinaryCreate|Throwable
     */
    public function assignToGroup(string $groupName, ?DateTimeInterface $assignedAt = null)
    {
        $this->assign($groupName, null, null, $assignedAt);
    }

    /**
     * @throws FailBinaryCreate|Throwable
     */
    public function addMessage($message, ?array $fileInfo = null)
    {
        if (!$message instanceof Response && !$message instanceof Comment) {
            return;
        }

//        if ($message instanceof Comment && $message->creator->id === $this->post->author->id) {
//            return;
//        }

        $messageStruct = $this->messageSerializer->serialize($this->post, $message);

        if ($fileInfo !== null) {
            $fileHandler = \eZClusterFileHandler::instance($fileInfo['filepath']);
            if (!$fileHandler->exists()){
                throw new \RuntimeException(sprintf("File path %s not found", $fileInfo['filepath']));
            }
            $messageStruct->attachments[] = [
                'name' => $fileInfo['original_filename'],
                'original_filename' => $fileInfo['original_filename'],
                'file' => base64_encode($fileHandler->fetchContents()),
                'mime_type' => $fileHandler->dataType(),
                'protocol_required' => false,
            ];
        }

        $this->assertExistApplication();

        try {
            $this->request(new GetApplicationMessageByText($messageStruct->message, $this->application['id']));
            $this->logger->debug('Message already pushed');
        } catch (MessageNotFound $e) {
            $this->request(new CreateApplicationMessage($this->application['id'], $messageStruct));
        }
    }

    /**
     * @throws FailBinaryCreate|Throwable
     */
    public function accept(?DateTimeInterface $assignedAt = null)
    {
        $this->assertExistApplication();
        if ((int)$this->application['status'] >= 7000) {
            $this->logger->debug(sprintf("Application %s already accepted", $this->application['id']));
            return;
        }
        $lastResponse = $this->post->responses->last();
        $this->request(
            new AcceptApplication($this->application['id'], $lastResponse ? $lastResponse->text : null),
            Credential::API_USER
        );
    }

    public function reopen()
    {
        $this->assertExistApplication();
        $this->request(
            new ReopenApplication($this->application['id']),
            Credential::API_USER
        );
    }

    /**
     * @throws FailBinaryCreate|Throwable
     */
    private function assign(
        string $groupName,
        ?string $userUuid = null,
        ?string $message = null,
        ?DateTimeInterface $assignedAt = null
    ) {
        $this->assertExistApplication();

        $this->logger->debug(sprintf('Assign application to group %s and user %s', $groupName, $userUuid ?? 'null'));

        try {
            $userGroup = $this->request(new GetUserGroupByName($groupName));
        } catch (UserGroupByNameNotFound $e) {
            $userGroup = $this->request(new CreateUserGroupWithName($groupName));
        }

        if ($this->application['user_group_id'] === $userGroup['id']){
            return;
        }

        $this->request(
            new AssignApplication(
                $this->application['id'],
                $userGroup['id'],
                $userUuid,
                $message,
                $assignedAt
            ),
            Credential::API_USER
        );
    }

    /**
     * @param Post\Field\Image|Post\Field\File $file
     * @return array
     * @throws FailBinaryCreate
     */
    private function uploadBinary(Post\Field $file): array
    {
        $struct = $this->binarySerializer->serialize($file);
        return $this->client->uploadBinary(
            $struct['path'],
            $struct['original_filename'],
            $struct['mime_type']
        );
    }

    /**
     * @throws Throwable
     */
    private function request(RequestHandlerInterface $requestHandler, ?string $as = null)
    {
        return ($this->client)($requestHandler, $as);
    }

    public function getClient(): HttpClient
    {
        return $this->client;
    }
}