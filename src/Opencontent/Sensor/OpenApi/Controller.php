<?php

namespace Opencontent\Sensor\OpenApi;

use GuzzleHttp\Exception\GuzzleException;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Exception\BaseException;
use Opencontent\Sensor\Api\Exception\DuplicateUuidException;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotAcceptableException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Message\Comment;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;
use Opencontent\Sensor\Api\Values\Post\Field\Image;
use Opencontent\Sensor\Api\Values\Post\WorkflowStatus;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;
use Opencontent\Sensor\Inefficiency\CategoryAdapter;
use Opencontent\Sensor\Inefficiency\PostAdapter;
use Opencontent\Sensor\Inefficiency\PostMessageAdapter;
use Opencontent\Sensor\OpenApi;
use Opencontent\Stanzadelcittadino\Client\Exceptions\FailPatchApplicationBinaryWithExternalId;
use Opencontent\Stanzadelcittadino\Client\Request\PatchApplicationMessageWithExternalId;
use SensorOpenApiControllerInterface;
use ezpRestMvcResult;
use Opencontent\Sensor\Api\SearchService;
use Opencontent\Sensor\Legacy\SearchService\QueryBuilder;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Parser\Parameter;
use Opencontent\Sensor\Legacy\Utils\MailValidator;

class Controller
{
    /**
     * @var OpenApi
     */
    private $apiSettings;

    /**
     * @var \Opencontent\Sensor\Legacy\Repository
     */
    private $repository;

    /**
     * @var SensorOpenApiControllerInterface
     */
    private $restController;

    /**
     * @var AbstractSerializer[]
     */
    private $serializer;

    private $geolocationIsRequired = false;

    public function __construct(OpenApi $apiSettings, SensorOpenApiControllerInterface $restController)
    {
        $this->apiSettings = $apiSettings;
        $this->repository = $this->apiSettings->getRepository();
        $this->restController = $restController;
        $this->serializer = new Serializer($this->apiSettings);
    }

    /**
     * @return bool
     */
    public function isGeolocationFieldRequired(): bool
    {
        return $this->geolocationIsRequired;
    }

    public function setGeolocationRequired(): void
    {
        $this->geolocationIsRequired = true;
    }

    public static function getSortFieldMap()
    {
        return [
            'id' => 'id',
            'published_at' => 'published',
            'modified_at' => 'modified',
            'subject' => 'subject',
            'type' => 'type',
        ];
    }

    private function cleanCommaSeperatedIntegers($csi)
    {
        $csi = explode(',', $csi);
        $csi = array_map('trim', $csi);
        $csi = array_map('intval', $csi);

        return implode(',', $csi);
    }

    public function loadPosts($authorId = null)
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');
        $authorFiscalCode = $this->getRequestParameter('authorFiscalCode');
        $categories = $this->getRequestParameter('categories');
        $areas = $this->getRequestParameter('areas');
        $status = $this->getRequestParameter('status');
        $type = $this->getRequestParameter('type');
        $channel = $this->getRequestParameter('channel');
        $publishedFrom = $this->getRequestParameter('publishedFrom');
        $publishedTo = $this->getRequestParameter('publishedTo');
        $modifiedFrom = $this->getRequestParameter('modifiedFrom');
        $modifiedTo = $this->getRequestParameter('modifiedTo');

        $parameters = [];

        $sortMap = self::getSortFieldMap();
        $sortField = 'id';
        if ($this->getRequestParameter('sortField')) {
            $sortField = $this->getRequestParameter('sortField');
            $parameters['sortField'] = $sortField;
        }
        if ($sortField && !isset($sortMap[$sortField])) {
            throw new InvalidArgumentException("Invalid sort field: $sortField");
        }
        $sortField = $sortMap[$sortField];

        $sortDirection = 'asc';
        if ($this->getRequestParameter('sortDirection')) {
            $sortDirection = $this->getRequestParameter('sortDirection');
            $parameters['sortDirection'] = $sortDirection;
        }
        if ($sortDirection != 'asc' && $sortDirection != 'desc') {
            throw new InvalidArgumentException("Invalid sort direction: $sortDirection");
        }

        if ($authorFiscalCode) {
            $parameters['authorFiscalCode'] = $authorFiscalCode;
        }

        if ($this->hasRequestAcceptTypes('application/vnd.geo+json')) {
            $parameters['format'] = 'geojson';
        }

        if ($limit > SearchService::MAX_LIMIT && !$this->hasRequestAcceptTypes('application/vnd.geo+json')) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $query = '';
        if ($q) {
            $query = 'q = "' . $q . '" and ';
            $parameters['q'] = $q;
        }
        if ($categories) {
            $query .= 'raw[submeta_category___id____si] in [' . $this->cleanCommaSeperatedIntegers(
                    $categories
                ) . '] and ';
            $parameters['categories'] = $categories;
        }
        if ($areas) {
            $query .= 'raw[submeta_area___id____si] in [' . $this->cleanCommaSeperatedIntegers($areas) . '] and ';
            $parameters['areas'] = $areas;
        }
        if ($status) {
            $query .= 'raw[sensor_status_lk] = "' . $status . '" and ';
            $parameters['status'] = $status;
        }
        if ($type) {
            $query .= 'raw[attr_type_s] = "' . $type . '" and ';
            $parameters['type'] = $type;
        }
        if ($channel) {
            $query .= 'raw[attr_on_behalf_of_mode_s] = "' . $channel . '" and ';
            $parameters['status'] = $channel;
        }
        if ($publishedFrom || $publishedTo) {
            if (!$publishedFrom) {
                $publishedFrom = '*';
            }
            if (!$publishedTo) {
                $publishedTo = '*';
            }
            $query .= "published range  [$publishedFrom, $publishedTo] and ";
            $parameters['publishedFrom'] = $publishedFrom;
            $parameters['publishedTo'] = $publishedTo;
        }

        if ($modifiedFrom || $modifiedTo) {
            if (!$modifiedFrom) {
                $modifiedFrom = '*';
            }
            if (!$modifiedTo) {
                $modifiedTo = '*';
            }
            $query .= "modified range  [$modifiedFrom, $modifiedTo] and ";
            $parameters['modifiedFrom'] = $modifiedFrom;
            $parameters['modifiedTo'] = $modifiedTo;
        }


        if ($authorId) {
            $query .= 'author_id = "' . (int)$authorId . '" ';
        }

        if ($offset !== null) {
            $query .= "limit $limit offset $offset ";
        } else {
            $query .= "limit $limit cursor [$cursor] ";
        }

        if ($sortField && $sortDirection) {
            $query .= "sort [$sortField=>$sortDirection] ";
        }

        $result = new ezpRestMvcResult();

        $searchResults = $this->repository->getSearchService()->searchPosts($query, $parameters);
        if ($this->hasRequestAcceptTypes('application/vnd.geo+json')) {
            $searchResults = (array)$searchResults;
            unset($searchResults['query']);
            unset($searchResults['nextPageQuery']);
            unset($searchResults['totalCount']);
            unset($searchResults['facets']);
            $result->variables = $searchResults;
        } else {
            $postSearchResults = [
                'self' => $this->restController->getBaseUri() . "/posts?" . $this->convertQueryInQueryParameters(
                        $searchResults->query,
                        $parameters
                    ),
                'next' => null,
                'items' => $this->serializer->setEmbedFields($this->getRequestParameter('embed'))->serializeItems(
                    $searchResults->searchHits
                ),
                'count' => (int)$searchResults->totalCount,
            ];
            if ($searchResults->nextPageQuery) {
                $postSearchResults['next'] = $this->restController->getBaseUri(
                    ) . "/posts?" . $this->convertQueryInQueryParameters($searchResults->nextPageQuery, $parameters);
            }
            $result->variables = $postSearchResults;
        }

        return $result;
    }

    public function createPost()
    {
        $postCreateStruct = $this->loadPostCreateStruct();
        return $this->createPostFromStruct($postCreateStruct);
    }

    public function createInefficiency()
    {
        $payload = $this->restController->getPayload();
        $inefficiency = PostAdapter::instance($this->repository, (array)$payload);
        if ($inefficiency->isValidPayload()) {
            $payload = $inefficiency->adaptPayload();
            $postCreateStruct = $this->loadPostStruct(new PostCreateStruct(), $payload);
            return $this->createPostFromStruct($postCreateStruct);
        }
        throw new NotAcceptableException('Can not handle application payload');
    }

    /**
     * @throws NotFoundException
     * @throws \Throwable
     * @throws GuzzleException
     * @throws NotAcceptableException
     * @throws BaseException
     */
    public function createInefficiencyMessage()
    {
        $payload = $this->restController->getPayload();
        $inefficiency = PostMessageAdapter::instance($this->repository, (array)$payload);

        try {
            $isValidPayload = $inefficiency->isValidPayload();
        }catch (\RuntimeException $e){
            header("HTTP/1.1 204 " . \ezpRestStatusResponse::$statusCodes[204]);
            $result = new ezpRestMvcResult();
            $result->variables = [];
            return $result;
        }

        if ($isValidPayload) {
            $post = $this->repository->getPostService()->loadPostByUuid($payload['application']);
            if (!$post instanceof Post) {
                throw new NotFoundException('post by uuid not found');
            }

            try {
                $action = new Action();
                $action->identifier = 'add_comment';
                $action->setParameter('text', $this->cleanMessageText($payload['message']));
                $action->setParameter('creator_id', $post->author->id); // @todo author validation?
                $action->setParameter('external_id', $payload['id']);
                $this->repository->getActionService()->runAction($action, $post);

                $binaries = [
                    'images' => [],
                    'files' => [],
                ];
                $attachments = $payload['attachments'] ?? [];
                foreach ($attachments as $attachment) {
                    $name = $attachment['name'];
                    $mimeData = \eZMimeType::findByURL($name);
                    $type = strpos($mimeData['name'], 'image/') !== false ? 'images' : 'files';
                    $data = $this->repository->getInefficiencyClient()->downloadBinary($attachment['url']);
                    if ($data) {
                        $binaries[$type][] = [
                            'file' => base64_encode($data),
                            'filename' => $attachment['original_name'],
                        ];
                    }
                }
                if (!empty($binaries['images'])) {
                    $this->repository->getPostService()->addImage($post, $binaries['images']);
                }
                if (!empty($binaries['files'])) {
                    $this->repository->getPostService()->addFile($post, $binaries['files']);
                }

                /** @var Comment $message */
                $message = $this->repository->getMessageService()->loadCommentCollectionByPost($post)->last();
                if ($this->repository instanceof \OpenPaSensorRepository) {
                    try {
                        ($this->repository->getInefficiencyClient())(
                            new PatchApplicationMessageWithExternalId($payload['application'], $payload['id'], $message->id)
                        );
                    } catch (FailPatchApplicationBinaryWithExternalId $e) {
                        $this->repository->getLogger()->error($e->getMessage());
                    }
                }
            }catch (DuplicateUuidException $e){
                $message = []; //@todo $this->repository->getMessageService()->loadMessageFromExternalId($e->getUuid());
            }

            header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
            $result = new ezpRestMvcResult();
            $result->variables = $this->serializer->serialize($message);

            return $result;
        }
        header("HTTP/1.1 204 " . \ezpRestStatusResponse::$statusCodes[204]);
        $result = new ezpRestMvcResult();
        $result->variables = [];
        return $result;
        //throw new NotAcceptableException('Can not handle message payload');
    }

    public function loadInefficiencyCategories()
    {
        $result = new ezpRestMvcResult();
        $result->variables = CategoryAdapter::instance($this->repository)->getCategories(
            $this->getRequestParameter('area')
        );
        $result->cache = new \ezcMvcResultCache();
        $result->cache->controls = ['public', 'max-age=60', 's-maxage=600'];
        $result->cache->pragma = 'cache';
        $result->cache->expire = (new \DateTime())->add(new \DateInterval('P1D'));

        return $result;
    }

    private function createPostFromStruct(PostCreateStruct $postCreateStruct)
    {
        $post = $this->repository->getPostService()->createPost($postCreateStruct);
        if ($postCreateStruct->imagePath) {
            $this->cleanupTempImage($postCreateStruct->imagePath);
        }
        if (count($postCreateStruct->imagePaths) > 0) {
            foreach ($postCreateStruct->imagePaths as $imagePath) {
                $this->cleanupTempImage($imagePath);
            }
        }

        header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($post);

        return $result;
    }

    public function getPostById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer
            ->setEmbedFields($this->getRequestParameter('embed'))
            ->serializeItem($this->loadPost());

        return $result;
    }

    public function updatePostById()
    {
        $postUpdateStruct = $this->loadPostUpdateStruct();
        $postUpdateStruct->setPost($this->loadPost());
        $post = $this->repository->getPostService()->updatePost($postUpdateStruct);
        if ($postUpdateStruct->imagePath) {
            $this->cleanupTempImage($postUpdateStruct->imagePath);
        }
        foreach ($postUpdateStruct->imagePaths as $imagePath) {
            $this->cleanupTempImage($imagePath);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($post);

        return $result;
    }

    public function getApproversByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->approvers);
        return $result;
    }

    public function setApproversToPostId()
    {
        $action = new Action();
        $action->identifier = 'add_approver';
        $action->setParameter('participant_ids', $this->restController->getPayload()['participant_ids']);
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->approvers);

        return $result;
    }

    public function getOwnersByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->owners);

        return $result;
    }

    public function setOwnersToPostId()
    {
        $action = new Action();
        $action->identifier = 'assign';
        $participantIds = (array)$this->restController->getPayload()['participant_ids'];
        if (in_array($this->repository->getCurrentUser()->id, $participantIds) && count($participantIds) === 1) {
            $action->identifier = 'auto_assign';
        }
        $action->setParameter('participant_ids', $participantIds);
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->owners);

        return $result;
    }

    public function getObserversByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->observers);

        return $result;
    }

    public function setObserversToPostId()
    {
        $action = new Action();
        $action->identifier = 'add_observer';
        $action->setParameter('participant_ids', $this->restController->getPayload()['participant_ids']);
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->observers);

        return $result;
    }

    public function getParticipantsByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->loadPost()->participants);

        return $result;
    }

    public function getPostParticipantUsersByParticipantId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = array_values(
            $this->loadPost()->participants->getParticipantById($this->restController->participantId)->users
        );

        return $result;
    }

    public function getCommentsByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->comments)];

        return $result;
    }

    public function addCommentsToPostId()
    {
        $post = $this->loadPost();
        $text = $this->restController->getPayload()['text'];

        $action = new Action();
        $action->identifier = 'add_comment';
        $action->setParameter('text', $this->cleanMessageText($text));
        $this->repository->getActionService()->runAction($action, $post);

        header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getMessageService()->loadCommentCollectionByPost($post)->last()
        );

        return $result;
    }

    public function editCommentsInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'edit_comment';
        $action->setParameter('id', $this->restController->commentId);
        $action->setParameter('text', $this->cleanMessageText($this->restController->getPayload()['text']));
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getMessageService()->loadCommentCollectionByPost($post)->getById(
                $this->restController->commentId
            )
        );

        return $result;
    }

    public function getPrivateMessagesByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->privateMessages)];

        return $result;
    }

    public function addPrivateMessageToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'send_private_message';
        $action->setParameter('text', $this->cleanMessageText($this->restController->getPayload()['text']));
        if (isset($this->restController->getPayload()['receivers'])) {
            $action->setParameter('participant_ids', $this->restController->getPayload()['receivers']);
        }
        $this->repository->getActionService()->runAction($action, $post);

        header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getMessageService()->loadPrivateMessageCollectionByPost($post)->last()
        );

        return $result;
    }

    public function editPrivateMessageInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'edit_message';
        $action->setParameter('id', $this->restController->privateMessageId);
        $action->setParameter('text', $this->cleanMessageText($this->restController->getPayload()['text']));
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getMessageService()->loadPrivateMessageCollectionByPost($post)->getById(
                $this->restController->privateMessageId
            )
        );

        return $result;
    }

    public function getResponsesByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->responses)];

        return $result;
    }

    public function addResponsesToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'add_response';
        $action->setParameter('text', $this->cleanMessageText($this->restController->getPayload()['text']));
        $this->repository->getActionService()->runAction($action, $post);

        header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getMessageService()->loadResponseCollectionByPost($post)->last()
        );

        return $result;
    }

    public function editResponsesInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'edit_response';
        $action->setParameter('id', $this->restController->responseId);
        $action->setParameter('text', $this->cleanMessageText($this->restController->getPayload()['text']));
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getMessageService()->loadResponseCollectionByPost($post)->getById(
                $this->restController->responseId
            )
        );

        return $result;
    }

    public function getAttachmentsByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->attachments)];

        return $result;
    }

    public function addAttachmentsToPostId()
    {
        $post = $this->loadPost();

        $files = $this->restController->getPayload()['files'];
        foreach ($files as $index => $file) {
            if ($files === '?') {
                unset($files[$index]);
            } elseif (!empty($file)) {
                if (filter_var($file['file'], FILTER_VALIDATE_URL)) {
                    $context = null;
                    $apiRequestHttpStreamContext = (array)\eZINI::instance('ocsensor.ini')->variable(
                        'SensorConfig',
                        'ApiRequestHttpStreamContext'
                    );
                    if (!empty($apiRequestHttpStreamContext)) {
                        $httpOpts = [];
                        foreach ($apiRequestHttpStreamContext as $key => $value) {
                            $httpOpts[$key] = $value;
                        }
                        $context = stream_context_create(['http' => $httpOpts]);
                    }
                    $files[$index]['file'] = base64_encode(file_get_contents($file['file'], false, $context));
                }
            }
        }

        $action = new Action();
        $action->identifier = 'add_attachment';
        $action->setParameter('files', $files);
        $this->repository->getActionService()->runAction($action, $post);

        header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->attachments)];

        return $result;
    }

    public function deleteAttachmentsInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'remove_attachment';
        $action->setParameter('files', [$this->restController->filename]);
        $this->repository->getActionService()->runAction($action, $post);

        header("HTTP/1.1 200 " . \ezpRestStatusResponse::$statusCodes[200]);
        $result = new ezpRestMvcResult();

        return $result;
    }

    public function getTimelineByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->timelineItems)];

        return $result;
    }

    public function getAreasByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = [
            'items' => $this->serializer->serializeItems($this->loadPost()->areas),
            'self' => $this->restController->getBaseUri() . "/post/{$this->restController->postId}/areas",
            'next' => null,
            'count' => count($this->loadPost()->areas),
        ];

        return $result;
    }

    public function setAreasToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'add_area';
        $action->setParameter('area_id', $this->restController->getPayload()['area_id']);
        $this->repository->getActionService()->runAction($action, $post);
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->areas)];

        return $result;
    }

    public function getCategoriesByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = [
            'items' => $this->serializer->serializeItems($this->loadPost()->categories),
            'self' => $this->restController->getBaseUri() . "/post/{$this->restController->postId}/categories",
            'next' => null,
            'count' => count($this->loadPost()->categories),
        ];


        return $result;
    }

    public function setCategoriesToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'add_category';
        $payload = $this->restController->getPayload();
        $action->setParameter('category_id', $payload['category_id']);
        $this->repository->getActionService()->runAction($action, $post);
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer->serializeItems($this->loadPost()->categories)];

        return $result;
    }

    public function getStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['identifier' => $this->loadPost()->status->identifier];

        return $result;
    }

    public function getWorkflowStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['identifier' => $this->loadPost()->workflowStatus->identifier];

        return $result;
    }

    public function setWorkflowStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $post = $this->loadPost();
        $newStatus = WorkflowStatus::instanceByIdentifier(
            $this->restController->getPayload()['identifier']
        );

        if ($newStatus->code === WorkflowStatus::CLOSED) {
            $action = new Action();
            $action->identifier = 'close';
            $this->repository->getActionService()->runAction($action, $post);
            $result->variables = ['identifier' => $this->loadPost()->workflowStatus->identifier];
        } elseif ($newStatus->code === WorkflowStatus::FIXED) {
            if ($this->repository->getCurrentUser()->permissions->hasPermission('can_force_fix')) {
                $action = new Action();
                $action->identifier = 'force_fix';
                $this->repository->getActionService()->runAction($action, $post);
                $result->variables = ['identifier' => $this->loadPost()->workflowStatus->identifier];
            } else {
                $action = new Action();
                $action->identifier = 'fix';
                $this->repository->getActionService()->runAction($action, $post);
                $result->variables = ['identifier' => $this->loadPost()->workflowStatus->identifier];
            }
        } elseif ($newStatus->code === WorkflowStatus::REOPENED) {
            $action = new Action();
            $action->identifier = 'reopen';
            $this->repository->getActionService()->runAction($action, $post);
            $result->variables = ['identifier' => $this->loadPost()->workflowStatus->identifier];
        } elseif ($newStatus->code === WorkflowStatus::READ) {
            $action = new Action();
            $action->identifier = 'read';
            $this->repository->getActionService()->runAction($action, $post);
            $result->variables = ['identifier' => $this->loadPost()->workflowStatus->identifier];
        } else {
            throw new InvalidArgumentException("Can not set workflow status to {$newStatus->identifier}");
        }

        return $result;
    }

    public function getPrivacyStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['identifier' => $this->loadPost()->privacy->identifier];

        return $result;
    }

    public function setPrivacyStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $post = $this->loadPost();
        $newStatus = $this->restController->getPayload()['identifier'];

        if ($newStatus === 'public') {
            $action = new Action();
            $action->identifier = 'make_public';
            $this->repository->getActionService()->runAction($action, $post);
            $result->variables = ['identifier' => $this->loadPost()->privacy->identifier];
        } elseif ($newStatus === 'private') {
            $action = new Action();
            $action->identifier = 'make_private';
            $this->repository->getActionService()->runAction($action, $post);
            $result->variables = ['identifier' => $this->loadPost()->privacy->identifier];
        } else {
            throw new InvalidArgumentException("Can not set privacy status {$newStatus}");
        }

        return $result;
    }

    public function getModerationStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['identifier' => $this->loadPost()->moderation->identifier];

        return $result;
    }

    public function setModerationStatusByPostId()
    {
        $result = new ezpRestMvcResult();
        $post = $this->loadPost();
        $newStatus = $this->restController->getPayload()['identifier'];

        $availableStatuses = [
            'waiting',
            'accepted',
            'refused',
        ];

        if (!in_array($newStatus, $availableStatuses)) {
            throw new InvalidArgumentException("Can not set moderation status {$newStatus}");
        }

        $action = new Action();
        $action->identifier = 'moderate';
        $action->setParameter('status', $newStatus);
        $this->repository->getActionService()->runAction($action, $post);
        $result->variables = ['identifier' => $this->loadPost()->moderation->identifier];

        return $result;
    }

    public function setExpiryByPostId()
    {
        $action = new Action();
        $action->identifier = 'set_expiry';
        $action->setParameter('expiry_days', (int)$this->restController->getPayload());
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = ['expiry_at' => $this->serializer->serialize($this->loadPost())['expiry_at']];
        return $result;
    }

    public function loadUsers()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $searchResults = $this->repository->getUserService()->loadUsers($q, $limit, $cursor);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q,
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/users?" . http_build_query($parameters),
            'next' => null,
            'items' => $this->serializer->serializeItems($searchResults['items']),
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $parameters['cursor'] = $searchResults['next'];
            $results['next'] = $this->restController->getBaseUri() . "/users?" . http_build_query($parameters);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createUser()
    {
        $payload = $this->restController->getPayload();
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem(
            $this->repository->getUserService()->createUser($payload)
        );
        return $result;
    }

    public function getUserById()
    {
        $result = new ezpRestMvcResult();
        // utilizzo la ricerca per il controllo dei permessi di accesso
        $userData = $this->repository->getUserService()->searchOne($this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);
        $result->variables = $this->serializer->serializeItem($user);

        return $result;
    }

    public function getUserByIdPosts()
    {
        // utilizzo la ricerca per il controllo dei permessi di accesso
        $userData = $this->repository->getUserService()->searchOne($this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);

        return $this->loadPosts($user->id);
    }

    public function getCurrentUser()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem($this->repository->getCurrentUser());

        return $result;
    }

    public function getCurrentUserPosts()
    {
        return $this->loadPosts($this->repository->getCurrentUser()->id);
    }

    public function updateUserById()
    {
        $userData = $this->repository->getUserService()->searchOne($this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);
        $payload = $this->restController->getPayload();

        return $this->updateUser($user, $payload);
    }

    public function patchUserById()
    {
        $userData = $this->repository->getUserService()->searchOne($this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);
        $payload = $this->restController->getPayload();

        return $this->patchUser($user, $payload);
    }

    public function updateCurrentUser()
    {
        $user = $this->repository->getCurrentUser();
        $payload = $this->restController->getPayload();

        return $this->updateUser($user, $payload);
    }

    public function patchCurrentUser()
    {
        $user = $this->repository->getCurrentUser();
        $payload = $this->restController->getPayload();

        return $this->patchUser($user, $payload);
    }

    private function updateUser($user, $payload)
    {
        $payload = $this->restController->getPayload();
        if (empty($payload['first_name'])) {
            throw new InvalidInputException("Field first_name is required");
        }
        if (empty($payload['last_name'])) {
            throw new InvalidInputException("Field last_name is required");
        }
        if (empty($payload['email'])) {
            throw new InvalidInputException("Field email is required");
        }
        if (!MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (\eZUser::fetchByEmail($payload['email']) && $user->email != $payload['email']) {
            throw new InvalidInputException("Email address already exists");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem(
            $this->repository->getUserService()->updateUser($user, $payload)
        );
        return $result;
    }

    private function patchUser($user, $payload)
    {
        $payload = $this->restController->getPayload();
        if (empty($payload)) {
            throw new InvalidInputException("Payload is empty");
        }
        if (isset($payload['email']) && !empty($payload['email']) && !MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (isset($payload['email']) && !empty($payload['email']) && \eZUser::fetchByEmail(
                $payload['email']
            ) && $user->email != $payload['email']) {
            throw new InvalidInputException("Email address already exists");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem(
            $this->repository->getUserService()->updateUser($user, $payload)
        );
        return $result;
    }

    public function loadOperators()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $searchResults = $this->repository->getOperatorService()->loadOperators($q, $limit, $cursor);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q,
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/operators?" . http_build_query($parameters),
            'next' => null,
            'items' => $this->serializer->serializeItems($searchResults['items']),
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $parameters['cursor'] = $searchResults['next'];
            $results['next'] = $this->restController->getBaseUri() . "/operators?" . http_build_query($parameters);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createOperator()
    {
        $payload = $this->restController->getPayload();

        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (empty($payload['role'])) {
            throw new InvalidInputException("Field role is required");
        }
        if (empty($payload['email'])) {
            throw new InvalidInputException("Field email is required");
        }
        if (!MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (\eZUser::fetchByEmail($payload['email'])) {
            throw new InvalidInputException("Email address already exists");
        }
        if (!empty($payload['groups'])) {
            foreach ($payload['groups'] as $groupId) {
                $this->repository->getGroupService()->loadGroup($groupId);
            }
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem(
            $this->repository->getOperatorService()->createOperator($payload)
        );
        return $result;
    }

    public function getOperatorById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem(
            $this->repository->getOperatorService()->loadOperator($this->restController->operatorId)
        );

        return $result;
    }

    public function updateOperatorById()
    {
        $operator = $this->repository->getOperatorService()->loadOperator($this->restController->operatorId);

        $payload = $this->restController->getPayload();
        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (empty($payload['role'])) {
            throw new InvalidInputException("Field role is required");
        }
        if (empty($payload['email'])) {
            throw new InvalidInputException("Field email is required");
        }
        if (!MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (\eZUser::fetchByEmail($payload['email']) && $operator->email != $payload['email']) {
            throw new InvalidInputException("Email address already exists");
        }
        if (!empty($payload['groups'])) {
            foreach ($payload['groups'] as $groupId) {
                $this->repository->getGroupService()->loadGroup($groupId);
            }
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem(
            $this->repository->getOperatorService()->updateOperator($operator, $payload)
        );
        return $result;
    }

    public function loadGroups()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $searchResults = $this->repository->getGroupService()->loadGroups($q, $limit, $cursor);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q,
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/groups?" . http_build_query($parameters),
            'next' => null,
            'items' => $searchResults['items'],
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $parameters['cursor'] = $searchResults['next'];
            $results['next'] = $this->restController->getBaseUri() . "/groups?" . http_build_query($parameters);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createGroup()
    {
        $payload = $this->restController->getPayload();

        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (empty($payload['email'])) {
            throw new InvalidInputException("Field email is required");
        }
        if (!MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getGroupService()->createGroup($payload);
        return $result;
    }

    public function getGroupById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getGroupService()->loadGroup($this->restController->groupId);

        return $result;
    }

    public function getGroupOperatorsById()
    {
        $result = new ezpRestMvcResult();
        $group = $this->repository->getGroupService()->loadGroup($this->restController->groupId);
        $limit = $this->getRequestParameter('limit');
        $cursor = $this->getRequestParameter('cursor');

        $searchResults = $this->repository->getOperatorService()->loadOperatorsByGroup($group, $limit, $cursor);
        $results = [
            'self' => $this->restController->getBaseUri(
                ) . "/groups/{$this->restController->groupId}/operators?limit=$limit&cursor=" . urlencode(
                    $searchResults['current']
                ),
            'next' => null,
            'items' => $searchResults['items'],
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri(
                ) . "/groups/{$this->restController->groupId}/operators?limit=$limit&cursor=" . urlencode(
                    $searchResults['next']
                );
        }

        $result->variables = $results;
        return $result;
    }

    public function updateGroupById()
    {
        $group = $this->repository->getGroupService()->loadGroup($this->restController->groupId);

        $payload = $this->restController->getPayload();
        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (empty($payload['email'])) {
            throw new InvalidInputException("Field email is required");
        }
        if (!MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }


        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getGroupService()->updateGroup($group, $payload);
        return $result;
    }

    public function loadCategories()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $searchResults = $this->repository->getCategoryService()->loadCategories($q, $limit, $cursor);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q,
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/categories?" . http_build_query($parameters),
            'next' => null,
            'items' => $this->serializer->serializeItems($searchResults['items']),
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $parameters['cursor'] = $searchResults['next'];
            $results['next'] = $this->restController->getBaseUri() . "/categories?" . http_build_query($parameters);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createCategory()
    {
        $payload = $this->restController->getPayload();

        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (!empty($payload['parent'])) {
            $this->repository->getCategoryService()->loadCategory($payload['parent']);
        }


        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getCategoryService()->createCategory($payload)
        );
        return $result;
    }

    public function getCategoryById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getCategoryService()->loadCategory($this->restController->categoryId)
        );

        return $result;
    }

    public function updateCategoryById()
    {
        $category = $this->repository->getCategoryService()->loadCategory($this->restController->categoryId);

        $payload = $this->restController->getPayload();
        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (!empty($payload['parent'])) {
            $this->repository->getCategoryService()->loadCategory($payload['parent']);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getCategoryService()->updateCategory($category, $payload);
        return $result;
    }

    public function loadAreas()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        // il controller openapi non espone la main area
        $excludeMainArea = $this->restController instanceof \SensorOpenApiController;
        $searchResults = $this->repository->getAreaService()->loadAreas($q, $limit, $cursor, $excludeMainArea);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q,
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/areas?" . http_build_query($parameters),
            'next' => null,
            'count' => (int)$searchResults['count'],
            'items' => $this->serializer->serializeItems($searchResults['items']),
        ];
        if ($searchResults['next']) {
            $parameters['cursor'] = $searchResults['next'];
            $results['next'] = $this->restController->getBaseUri() . "/areas?" . http_build_query($parameters);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createArea()
    {
        $payload = $this->restController->getPayload();

        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->repository->getAreaService()->createArea($payload));
        return $result;
    }

    public function getAreaById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize(
            $this->repository->getAreaService()->loadArea($this->restController->areaId)
        );
        return $result;
    }

    public function updateAreaById()
    {
        $area = $this->repository->getAreaService()->loadArea($this->restController->areaId);

        $payload = $this->restController->getPayload();
        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getAreaService()->updateArea($area, $payload);
        return $result;
    }

    public function loadStats()
    {
        $result = new ezpRestMvcResult();
        $factories = [];
        foreach ($this->repository->getStatisticsService()->getStatisticFactories(true) as $factory) {
            $factories[] = $factory->getIdentifier();
        }
        $result->variables = ['items', $factories];
        return $result;
    }

    public function getStatByIdentifier()
    {
        $result = new ezpRestMvcResult();
        $factory = $this->repository->getStatisticsService()->getStatisticFactoryByIdentifier(
            $this->restController->statIdentifier
        );
        $parameters = $this->restController->getRequest()->get;
        $factory->setParameters($parameters);
        $format = isset($parameters['format']) ? $parameters['format'] : 'default';
        $result->variables = $factory->getDataByFormat($format);

        return $result;
    }

    public function getUserStatByIdentifier()
    {
        $result = new ezpRestMvcResult();
        $factory = $this->repository->getStatisticsService()->getStatisticFactoryByIdentifier(
            $this->restController->statIdentifier
        );
        $factory->setAuthorFiscalCode($this->restController->authorFiscalCode);
        $factory->setParameter('interval', $this->getRequestParameter('interval'));
        $factory->setParameter('category', $this->getRequestParameter('category'));
        $factory->setParameter('area', $this->getRequestParameter('area'));
        $factoryItem = [
            'identifier' => $factory->getIdentifier(),
            'name' => $factory->getName(),
            'description' => $factory->getDescription(),
            'data' => $factory->getData(),
        ];
        $result->variables = $factoryItem;
        return $result;
    }

    public function loadFaqs()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $searchResults = $this->repository->getFaqService()->loadFaqs($q, $limit, $cursor);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q,
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/faq?" . http_build_query($parameters),
            'next' => null,
            'items' => $searchResults['items'],
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $parameters['cursor'] = $searchResults['next'];
            $results['next'] = $this->restController->getBaseUri() . "/faq?" . http_build_query($parameters);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createFaq()
    {
        $payload = $this->restController->getPayload();

        if (empty($payload['question'])) {
            throw new InvalidInputException("Field question is required");
        }
        if (empty($payload['answer'])) {
            throw new InvalidInputException("Field answer is required");
        }
        if (empty($payload['category'])) {
            throw new InvalidInputException("Field category is required");
        }
        $this->repository->getCategoryService()->loadCategory((int)$payload['category']);

        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getFaqService()->createFaq($payload);

        return $result;
    }

    public function getFaqById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getFaqService()->loadFaq($this->restController->faqId);

        return $result;
    }

    public function updateFaqById()
    {
        $faq = $this->repository->getFaqService()->loadFaq($this->restController->faqId);
        $payload = $this->restController->getPayload();

        if (empty($payload['question'])) {
            throw new InvalidInputException("Field question is required");
        }
        if (empty($payload['answer'])) {
            throw new InvalidInputException("Field answer is required");
        }
        if (empty($payload['category'])) {
            throw new InvalidInputException("Field category is required");
        }
        $this->repository->getCategoryService()->loadCategory((int)$payload['category']);

        $result = new ezpRestMvcResult();
        $result->variables = $this->repository->getFaqService()->updateFaq($faq, $payload);

        return $result;
    }

    public function loadTypes()
    {
        $types = $this->repository->getPostTypeService()->loadPostTypes();
        $results = [
            'self' => $this->restController->getBaseUri() . "/types",
            'next' => null,
            'items' => $types,
            'count' => count($types),
        ];

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function getTypeByIdentifier()
    {
        foreach ($this->repository->getPostTypeService()->loadPostTypes() as $type) {
            if ($this->restController->typeIdentifier == $type->identifier) {
                $result = new ezpRestMvcResult();
                $result->variables = $type->jsonSerialize();

                return $result;
            }
        }

        throw new NotFoundException();
    }

    public function loadUserGroups()
    {
        $limit = (int)$this->getRequestParameter('limit');
        if ($limit === 0) {
            $limit = 10;
        }
        $offset = (int)$this->getRequestParameter('offset');

        $countUserGroups = \eZContentObjectTreeNode::subTreeCountByNodeID([
            'ClassFilterType' => 'array',
            'ClassFilterArray' => ['user_group'],
            'AttributeFilter' => [
                [
                    'contentobject_id',
                    '!=',
                    $this->repository->getOperatorsRootNode()->attribute('contentobject_id'),
                ],
            ],
        ], \eZINI::instance()->variable("UserSettings", "DefaultUserPlacement"));

        /** @var \eZContentObjectTreeNode[] $userGroups */
        $userGroups = \eZContentObjectTreeNode::subTreeByNodeID([
            'ClassFilterType' => 'include',
            'ClassFilterArray' => ['user_group'],
            'LoadDataMap' => false,
            'Limit' => $limit,
            'Offset' => $offset,
            'AttributeFilter' => [
                [
                    'contentobject_id',
                    '!=',
                    $this->repository->getOperatorsRootNode()->attribute('contentobject_id'),
                ],
            ],
        ], \eZINI::instance()->variable("UserSettings", "DefaultUserPlacement"));

        $items = [];
        foreach ($userGroups as $userGroup) {
            $items[] = [
                'id' => (int)$userGroup->attribute('contentobject_id'),
                'name' => $userGroup->attribute('name'),
            ];
        }

        $next = null;
        if (($limit + $offset) < $countUserGroups) {
            $nextOffset = $offset + $limit;
            $next = $this->restController->getBaseUri() . "/user-groups?limit=$limit&offset=$nextOffset";
        }

        $result = new ezpRestMvcResult();
        $result->variables = [
            'self' => $this->restController->getBaseUri() . "/user-groups?limit=$limit&offset=$offset",
            'next' => $next,
            'items' => $items,
            'count' => (int)$countUserGroups,
        ];

        return $result;
    }

    public function getUserGroupById()
    {
        $groups = $this->repository->getMembersAvailableGroups();
        if (isset($groups[(int)$this->restController->userGroupId])) {
            $result = new ezpRestMvcResult();
            $result->variables = [
                'id' => (int)$this->restController->userGroupId,
                'name' => $groups[(int)$this->restController->userGroupId]['name'],
            ];

            return $result;
        }

        throw new NotFoundException();
    }

    private function loadPost()
    {
        $id = $this->restController->postId;
        if (is_numeric($id)) {
            $post = $this->repository->getPostService()->loadPost($id);
        } else {
            $post = $this->repository->getPostService()->loadPostByUuid($id);
        }

        $this->repository->getActionService()->runAction(
            new Action('read'),
            $post
        );

        return $post;
    }

    /**
     * @return PostCreateStruct|PostUpdateStruct
     * @throws InvalidArgumentException
     * @throws InvalidInputException
     */
    private function loadPostUpdateStruct()
    {
        $postStruct = new PostUpdateStruct();
        return $this->loadPostStruct($postStruct, $this->restController->getPayload());
    }

    /**
     * @return PostCreateStruct|PostUpdateStruct
     * @throws InvalidArgumentException
     * @throws InvalidInputException
     */
    private function loadPostCreateStruct()
    {
        $postStruct = new PostCreateStruct();
        return $this->loadPostStruct($postStruct, $this->restController->getPayload());
    }

    /**
     * @param PostCreateStruct|PostUpdateStruct $postCreateStruct
     * @return PostCreateStruct|PostUpdateStruct
     * @throws InvalidArgumentException
     * @throws InvalidInputException
     */
    private function loadPostStruct($postCreateStruct, $payload)
    {
        if (empty($payload['subject'])) {
            throw new InvalidInputException("Field subject is required");
        }
        $postCreateStruct->subject = $payload['subject'];

        if (empty($payload['description'])) {
            throw new InvalidInputException("Field description is required");
        }
        $postCreateStruct->description = $payload['description'];

        if (isset($payload['address']) && is_array($payload['address'])) {
            if (isset($payload['address']['latitude']) && $payload['address']['latitude'] === '0E-15') {
                $payload['address']['latitude'] = '0';
            }
            if (isset($payload['address']['longitude']) && $payload['address']['longitude'] === '0E-15') {
                $payload['address']['longitude'] = '0';
            }
            if (empty($payload['address']['address']) || empty($payload['address']['latitude']) || empty($payload['address']['longitude'])) {
                throw new InvalidInputException("Field address has wrong format");
            }
            $geoLocation = new GeoLocation();
            $geoLocation->address = $payload['address']['address'];
            $geoLocation->latitude = $payload['address']['latitude'];
            $geoLocation->longitude = $payload['address']['longitude'];
            $postCreateStruct->geoLocation = $geoLocation;
        } elseif ($this->isGeolocationFieldRequired()) {
            throw new InvalidInputException("Field address is required");
        }

        if (isset($payload['area'])) {
            if (is_array($payload['area'])) {
                $postCreateStruct->areas = [(int)$payload['area'][0]];
            } elseif (is_numeric($payload['area'])) {
                $postCreateStruct->areas = [(int)$payload['area']];
            }
        } elseif (isset($payload['areas']) && is_array($payload['areas'])) {
            $postCreateStruct->areas = (array)$payload['areas'];
        }

        if (empty($postCreateStruct->areas) && $postCreateStruct->geoLocation instanceof GeoLocation) {
            $area = $this->repository->getAreaService()->findAreaByGeoLocation($postCreateStruct->geoLocation);
            if ($area instanceof Area) {
                $postCreateStruct->areas = [(int)$area->id];
            }
        }

        if (isset($payload['category']) && !empty($payload['category'])) {
            $postCreateStruct->categories = [(int)$payload['category']];
        }

        if ($this->apiSettings->getRepository()->getSensorSettings()->get('HideTypeChoice')) {
            $postCreateStruct->type = $this->repository->getPostTypeService()->loadPostTypes()[0]->identifier;
        } elseif (isset($payload['type'])) {
            $apiTypeMap = $this->apiSettings->getRepository()->getSensorSettings()->get('ApiTypeMap');
            $postCreateStruct->type = isset($apiTypeMap[$payload['type']]) ? $apiTypeMap[$payload['type']] : $payload['type'];
        }

        if ($this->apiSettings->getRepository()->getSensorSettings()->get('HidePrivacyChoice')) {
            $postCreateStruct->privacy = 'private';
        } else {
            $postCreateStruct->privacy = isset($payload['is_private']) && $payload['is_private'] ? 'private' : 'public';
        }

        if (isset($payload['images'])) {
            $imagePaths = [];
            foreach ($payload['images'] as $index => $image) {
                if ($image === '?') {
                    unset($payload['images'][$index]);
                } elseif (!empty($image)) {
                    $this->isValidUpload($image);
                }
            }
            foreach ($payload['images'] as $image) {
                if (!empty($image)) {
                    $imagePaths[] = $this->downloadBinary($image);
                }
            }
            if (!empty($imagePaths)) {
                $postCreateStruct->imagePaths = $imagePaths;
            }
        } elseif (isset($payload['image'])) {
            if (!empty($payload['image'])) {
                $this->isValidUpload($payload['image']);
                $postCreateStruct->imagePath = $this->downloadBinary($payload['image']);
            }
        }

        if (isset($payload['files'])) {
            $filesPaths = [];
            foreach ($payload['files'] as $index => $file) {
                if ($file === '?') {
                    unset($payload['files'][$index]);
                } elseif (!empty($file)) {
                    $this->isValidUpload($file, 'files');
                }
            }

            foreach ($payload['files'] as $file) {
                if (!empty($file)) {
                    $filesPaths[] = $this->downloadBinary($file);
                }
            }
            if (!empty($filesPaths)) {
                $postCreateStruct->filePaths = $filesPaths;
            }
        }

        if (isset($payload['meta'])) {
            $meta = $payload['meta'];
            $postCreateStruct->meta = is_string($meta) ? $meta : json_encode($meta);
        }

        if (isset($payload['author'])) {
            $postCreateStruct->author = $payload['author'];
        } elseif (isset($payload['author_email'])) {
            $postCreateStruct->author = $payload['author_email'];
        }

        if (isset($payload['channel'])) {
            $postCreateStruct->channel = (string)$payload['channel'];
        }

        if (isset($payload['uuid']) && $postCreateStruct instanceof PostCreateStruct) {
            $postCreateStruct->uuid = $payload['uuid'];
        }

        return $postCreateStruct;
    }

    private function isValidUpload($image, $field = 'images')
    {
        if (!filter_var($image, FILTER_VALIDATE_URL) && (empty($image['filename']) || empty($image['file']))) {
            throw new InvalidInputException("Field $field has wrong format");
        }

        return true;
    }

    private function downloadBinary($image)
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $context = null;
            $apiRequestHttpStreamContext = (array)\eZINI::instance('ocsensor.ini')->variable(
                'SensorConfig',
                'ApiRequestHttpStreamContext'
            );
            if (!empty($apiRequestHttpStreamContext)) {
                $httpOpts = [];
                foreach ($apiRequestHttpStreamContext as $key => $value) {
                    $httpOpts[$key] = $value;
                }
                $context = stream_context_create(['http' => $httpOpts]);
            }
            $imagePath = \eZSys::cacheDirectory() . '/' . basename($image);
            \eZFile::create(basename($imagePath), dirname($imagePath), file_get_contents($image, false, $context));
        } else {
            /** @var \eZDFSFileHandler $fileHandler */
            $fileHandler = \eZClusterFileHandler::instance($image['file']);
            if ($fileHandler->exists()) {
                $fileHandler->fetch();
                $imagePath = $image['file'];
            } else {
                $imagePath = \eZSys::cacheDirectory() . '/' . $image['filename'];
                \eZFile::create(basename($imagePath), dirname($imagePath), base64_decode($image['file']));
            }
        }
        return $imagePath;
    }

    private function cleanupTempImage($filepath)
    {
        /** @var \eZDFSFileHandler $fileHandler */
        $fileHandler = \eZClusterFileHandler::instance($filepath);
        if ($fileHandler->exists()) {
            $fileHandler->delete();
            $fileHandler->purge();
        }
        @unlink($filepath);
    }

    private function getRequestParameter($name)
    {
        $parameters = array_merge(
            $this->restController->getRequest()->variables,
            $this->restController->getRequest()->get
        );

        if ($name == 'embed') {
            return isset($parameters['embed']) ? explode(',', $parameters['embed']) : [];
        }

        return isset($parameters[$name]) ? $parameters[$name] : null;
    }

    private function hasRequestAcceptTypes($mediaType)
    {
        if ($this->restController->getRequest()->accept instanceof \ezcMvcRequestAccept) {
            return in_array($mediaType, $this->restController->getRequest()->accept->types);
        }

        return false;
    }

    private function convertQueryInQueryParameters($query, $extraParameters = [])
    {
        try {
            $queryBuilder = new QueryBuilder($this->repository->getPostApiClass());
            $queryObject = $queryBuilder->instanceQuery($query);
            $queryObject->parse();

            $convertedQuery = new \ArrayObject();
            foreach ($queryObject->getFilters() as $item) {
                $this->parseQueryItem($item, $convertedQuery);
            }

            foreach ($queryObject->getParameters() as $item) {
                $this->parseQueryItem($item, $convertedQuery);
            }

            $convertedQuery = $convertedQuery->getArrayCopy();
            $data = [];

            $parameters = array_merge(
                $this->restController->getRequest()->variables,
                $this->restController->getRequest()->get
            );

            foreach ($parameters as $key => $value) {
                if (isset($convertedQuery[$key])) {
                    $data[$key] = is_array($convertedQuery[$key]) ? $convertedQuery[$key][0] : $convertedQuery[$key];
                }
            }
            if (isset($parameters['embed'])) {
                $data['embed'] = $parameters['embed'];
            }

            foreach ($extraParameters as $key => $value) {
                $data[$key] = $value;
            }

            return http_build_query($data);
        } catch (\Exception $e) {
            return '';
        }
    }

    private function parseQueryItem(Item $item, $convertedQuery)
    {
        if ($item->hasSentences()) {
            foreach ($item->getSentences() as $sentence) {
                $value = $sentence->getValue();

                if ($sentence instanceof Parameter) {
                    $field = (string)$sentence->getKey();
                    $convertedQuery[$field] = $this->recursiveTrim($value);
                } else {
                    $field = (string)$sentence->getField();
                    if (!isset($convertedQuery['filter'])) {
                        $convertedQuery['filter'] = [];
                    }
                    $convertedQuery['filter'][$field] = $this->recursiveTrim($value);
                }
            }
            if ($item->hasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $this->parseQueryItem($child, $convertedQuery);
                }
            }
        }
    }

    private function recursiveTrim($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'recursiveTrim'], $value);
        } else {
            return trim($value, "'");
        }
    }

    private function cleanMessageText($text)
    {
        return strip_tags(html_entity_decode($text));
    }

}
