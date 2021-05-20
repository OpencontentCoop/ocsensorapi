<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;
use Opencontent\Sensor\Api\Values\Post\Field\Image;
use Opencontent\Sensor\Api\Values\Post\WorkflowStatus;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;
use Opencontent\Sensor\OpenApi;
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

    public function __construct(OpenApi $apiSettings, SensorOpenApiControllerInterface $restController)
    {
        $this->apiSettings = $apiSettings;
        $this->repository = $this->apiSettings->getRepository();
        $this->restController = $restController;
        $this->serializer = new Serializer($this->apiSettings);
    }

    public function loadPosts($authorId = null)
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');
        $authorFiscalCode = $this->getRequestParameter('authorFiscalCode');
        $parameters = [];
        if ($authorFiscalCode){
            $parameters['authorFiscalCode'] = $authorFiscalCode;
        }

        if ($this->hasRequestAcceptTypes('application/vnd.geo+json')){
            $parameters['format'] = 'geojson';
        }

        if ($limit > SearchService::MAX_LIMIT && !$this->hasRequestAcceptTypes('application/vnd.geo+json')) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $query = '';
        if ($q) {
            $query = 'q = "' . $q . '" and ';
        }
        if ($authorId){
            $query .= 'author_id = "' . (int)$authorId . '" ';
        }
        if ($offset !== null) {
            $query .= "limit $limit offset $offset sort [id=>asc]";
        } else {
            $query .= "limit $limit cursor [$cursor] sort [id=>asc]";
        }

        $result = new ezpRestMvcResult();

        $searchResults = $this->repository->getSearchService()->searchPosts($query, $parameters);
        if ($this->hasRequestAcceptTypes('application/vnd.geo+json')){
            $searchResults = (array) $searchResults;
            unset($searchResults['query']);
            unset($searchResults['nextPageQuery']);
            unset($searchResults['totalCount']);
            unset($searchResults['facets']);
            $result->variables = $searchResults;
        }else {
            $postSearchResults = [
                'self' => $this->restController->getBaseUri() . "/posts?" . $this->convertQueryInQueryParameters($searchResults->query, $this->getRequestParameters(), $parameters),
                'next' => null,
                'items' => $this->serializer->setEmbedFields($this->getRequestParameter('embed'))->serializeItems($searchResults->searchHits),
                'count' => (int)$searchResults->totalCount,
            ];
            if ($searchResults->nextPageQuery) {
                $postSearchResults['next'] = $this->restController->getBaseUri() . "/posts?" . $this->convertQueryInQueryParameters($searchResults->nextPageQuery, $this->getRequestParameters(), $parameters);
            }
            $result->variables = $postSearchResults;
        }

        return $result;
    }

    public function createPost()
    {
        $postCreateStruct = $this->loadPostCreateStruct();

        $post = $this->repository->getPostService()->createPost($postCreateStruct);
        if ($postCreateStruct->imagePath) @unlink($postCreateStruct->imagePath);
        if (count($postCreateStruct->imagePaths) > 0) {
            foreach ($postCreateStruct->imagePaths as $imagePath) {
                @unlink($imagePath);
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
        if ($postUpdateStruct->imagePath) @unlink($postUpdateStruct->imagePath);
        foreach ($postUpdateStruct->imagePaths as $imagePath){
            @unlink($imagePath);
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
        $action->setParameter('participant_ids', $this->restController->getPayload()['participant_ids']);
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
        $result->variables = array_values($this->loadPost()->participants->getParticipantById($this->restController->participantId)->users);

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
        $action = new Action();
        $action->identifier = 'add_comment';
        $action->setParameter('text', $this->cleanMessageText($this->restController->getPayload()['text']));
        $this->repository->getActionService()->runAction($action, $post);

        header("HTTP/1.1 201 " . \ezpRestStatusResponse::$statusCodes[201]);
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->repository->getMessageService()->loadCommentCollectionByPost($post)->last());

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
        $result->variables = $this->serializer->serialize($this->repository->getMessageService()->loadCommentCollectionByPost($post)->getById($this->restController->commentId));

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
        $result->variables = $this->serializer->serialize($this->repository->getMessageService()->loadPrivateMessageCollectionByPost($post)->last());

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
        $result->variables = $this->serializer->serialize($this->repository->getMessageService()->loadPrivateMessageCollectionByPost($post)->getById($this->restController->privateMessageId));

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
        $result->variables = $this->serializer->serialize($this->repository->getMessageService()->loadResponseCollectionByPost($post)->last());

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
        $result->variables = $this->serializer->serialize($this->repository->getMessageService()->loadResponseCollectionByPost($post)->getById($this->restController->responseId));

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
        $action = new Action();
        $action->identifier = 'add_attachment';
        $action->setParameter('files', $this->restController->getPayload()['files']);
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
            'refused'
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
            'q' => $q
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
        $result->variables = $this->serializer->serializeItem($this->repository->getUserService()->createUser($payload));
        return $result;
    }

    public function getUserById()
    {
        $result = new ezpRestMvcResult();
        // utilizzo la ricerca per il controllo dei permessi di accesso
        $userData = $this->repository->getUserService()->searchOne('id = ' . $this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);
        $result->variables = $this->serializer->serializeItem($user);

        return $result;
    }

    public function getUserByIdPosts()
    {
        // utilizzo la ricerca per il controllo dei permessi di accesso
        $userData = $this->repository->getUserService()->searchOne('id = ' . $this->restController->userId);
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
        $userData = $this->repository->getUserService()->searchOne('id = ' . $this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);
        $payload = $this->restController->getPayload();

        return $this->updateUser($user, $payload);
    }

    public function patchUserById()
    {
        $userData = $this->repository->getUserService()->searchOne('id = ' . $this->restController->userId);
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
        $result->variables = $this->serializer->serializeItem($this->repository->getUserService()->updateUser($user, $payload));
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
        if (isset($payload['email']) && !empty($payload['email']) && \eZUser::fetchByEmail($payload['email']) && $user->email != $payload['email']) {
            throw new InvalidInputException("Email address already exists");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem($this->repository->getUserService()->updateUser($user, $payload));
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
            'q' => $q
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
        $result->variables = $this->serializer->serializeItem($this->repository->getOperatorService()->createOperator($payload));
        return $result;
    }

    public function getOperatorById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serializeItem($this->repository->getOperatorService()->loadOperator($this->restController->operatorId));

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
        $result->variables = $this->serializer->serializeItem($this->repository->getOperatorService()->updateOperator($operator, $payload));
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
            'q' => $q
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
            'self' => $this->restController->getBaseUri() . "/groups/{$this->restController->groupId}/operators?limit=$limit&cursor=" . urlencode($searchResults['current']),
            'next' => null,
            'items' => $searchResults['items'],
            'count' => (int)$searchResults['count'],
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri() . "/groups/{$this->restController->groupId}/operators?limit=$limit&cursor=" . urlencode($searchResults['next']);
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
            'q' => $q
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
        $result->variables = $this->serializer->serialize($this->repository->getCategoryService()->createCategory($payload));
        return $result;
    }

    public function getCategoryById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer->serialize($this->repository->getCategoryService()->loadCategory($this->restController->categoryId));

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

        $searchResults = $this->repository->getAreaService()->loadAreas($q, $limit, $cursor);
        $parameters = [
            'limit' => $limit,
            'cursor' => $searchResults['current'],
            'q' => $q
        ];
        $results = [
            'self' => $this->restController->getBaseUri() . "/areas?" . http_build_query($parameters),
            'next' => null,
            'items' => $this->serializer->serializeItems($searchResults['items']),
            'count' => (int)$searchResults['count'],
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
        $result->variables = $this->serializer->serialize($this->repository->getAreaService()->loadArea($this->restController->areaId));
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
        $factory = $this->repository->getStatisticsService()->getStatisticFactoryByIdentifier($this->restController->statIdentifier);
        $factory->setParameter('interval', $this->getRequestParameter('interval'));
        $factory->setParameter('category', $this->getRequestParameter('category'));
        $factory->setParameter('area', $this->getRequestParameter('area'));
        $factoryItem = [
            'identifier' => $factory->getIdentifier(),
            'name' => $factory->getName(),
            'description' => $factory->getDescription(),
            'data' => $factory->getData()
        ];
        $result->variables = $factoryItem;
        return $result;
    }

    public function getUserStatByIdentifier()
    {
        $result = new ezpRestMvcResult();
        $factory = $this->repository->getStatisticsService()->getStatisticFactoryByIdentifier($this->restController->statIdentifier);
        $factory->setAuthorFiscalCode($this->restController->authorFiscalCode);
        $factory->setParameter('interval', $this->getRequestParameter('interval'));
        $factory->setParameter('category', $this->getRequestParameter('category'));
        $factory->setParameter('area', $this->getRequestParameter('area'));
        $factoryItem = [
            'identifier' => $factory->getIdentifier(),
            'name' => $factory->getName(),
            'description' => $factory->getDescription(),
            'data' => $factory->getData()
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
            'q' => $q
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
        foreach ($this->repository->getPostTypeService()->loadPostTypes() as $type){
            if ($this->restController->typeIdentifier == $type->identifier){
                $result = new ezpRestMvcResult();
                $result->variables = $type->jsonSerialize();

                return $result;
            }
        }

        throw new NotFoundException();
    }

    private function loadPost()
    {
        return $this->repository->getSearchService()->searchPost($this->restController->postId);
    }

    /**
     * @return PostCreateStruct|PostUpdateStruct
     * @throws InvalidArgumentException
     * @throws InvalidInputException
     */
    private function loadPostUpdateStruct()
    {
        $postStruct = new PostUpdateStruct();
        return $this->loadPostStruct($postStruct);
    }

    /**
     * @return PostCreateStruct|PostUpdateStruct
     * @throws InvalidArgumentException
     * @throws InvalidInputException
     */
    private function loadPostCreateStruct()
    {
        $postStruct = new PostCreateStruct();
        return $this->loadPostStruct($postStruct);
    }

    /**
     * @param PostCreateStruct|PostUpdateStruct $postCreateStruct
     * @return PostCreateStruct|PostUpdateStruct
     * @throws InvalidArgumentException
     * @throws InvalidInputException
     */
    private function loadPostStruct($postCreateStruct)
    {
        $payload = $this->restController->getPayload();

        if (empty($payload['subject'])) {
            throw new InvalidInputException("Field subject is required");
        }
        $postCreateStruct->subject = $payload['subject'];

        if (empty($payload['description'])) {
            throw new InvalidInputException("Field description is required");
        }
        $postCreateStruct->description = $payload['description'];

        if (isset($payload['address']) && is_array($payload['address'])) {
            if (empty($payload['address']['address']) || empty($payload['address']['latitude']) || empty($payload['address']['longitude'])) {
                throw new InvalidInputException("Field address has wrong format");
            }
            $geoLocation = new GeoLocation();
            $geoLocation->address = $payload['address']['address'];
            $geoLocation->latitude = $payload['address']['latitude'];
            $geoLocation->longitude = $payload['address']['longitude'];
            $postCreateStruct->geoLocation = $geoLocation;
        }

        if (isset($payload['area']) && is_array($payload['area'])) {
            $postCreateStruct->areas = [(int)$payload['area'][0]];
        }elseif (isset($payload['areas']) && is_array($payload['areas'])) {
            $postCreateStruct->areas = (array)$payload['areas'];
        }

        if (isset($payload['category']) && !empty($payload['category'])) {
            $postCreateStruct->categories = [(int)$payload['category']];
        }

        if ($this->apiSettings->getRepository()->getSensorSettings()->get('HideTypeChoice')){
            $postCreateStruct->type = $this->repository->getPostTypeService()->loadPostTypes()[0]->identifier;
        }elseif (isset($payload['type'])) {
            $postCreateStruct->type = $payload['type'];
        }

        if ($this->apiSettings->getRepository()->getSensorSettings()->get('HidePrivacyChoice')){
            $postCreateStruct->privacy = 'private';
        }else {
            $postCreateStruct->privacy = isset($payload['is_private']) && $payload['is_private'] ? 'private' : 'public';
        }

        if (isset($payload['images'])) {
            foreach ($payload['images'] as $image) {
                $this->isValidImage($image);
            }
            foreach ($payload['images'] as $image) {
                $imagePaths[] = $this->downloadImage($image);
            }
            $postCreateStruct->imagePaths = $imagePaths;

        }elseif (isset($payload['image'])) {
            $this->isValidImage($payload['image']);
            $postCreateStruct->imagePath = $this->downloadImage($payload['image']);
        }

        if (isset($payload['meta'])) {
            $meta = $payload['meta'];
            $postCreateStruct->meta = is_string($meta) ? $meta : json_encode($meta);
        }

        if (isset($payload['author'])) {
            $postCreateStruct->author = $payload['author'];
        }elseif (isset($payload['author_email'])) {
            $postCreateStruct->author = $payload['author_email'];
        }

        if (isset($payload['channel'])) {
            $postCreateStruct->channel = (string)$payload['channel'];
        }

        return $postCreateStruct;
    }

    private function isValidImage($image)
    {
        if (!filter_var($image, FILTER_VALIDATE_URL) && (empty($image['filename']) || empty($image['file']))) {
            throw new InvalidInputException("Field images has wrong format");
        }

        return true;
    }

    private function downloadImage($image)
    {
        if (filter_var($image, FILTER_VALIDATE_URL)){
            $context = null;
            $apiRequestHttpStreamContext = (array)\eZINI::instance('ocsensor.ini')->variable('SensorConfig', 'ApiRequestHttpStreamContext');
            if (!empty($apiRequestHttpStreamContext)) {
                $httpOpts = [];
                foreach ($apiRequestHttpStreamContext as $key => $value){
                    $httpOpts[$key] = $value;
                }
                $context = stream_context_create(['http' => $httpOpts]);
            }
            $imagePath = \eZSys::cacheDirectory() . '/' . basename($image);
            \eZFile::create(basename($imagePath), dirname($imagePath), file_get_contents($image, false, $context));
        }else {
            $imagePath = \eZSys::cacheDirectory() . '/' . $image['filename'];
            \eZFile::create(basename($imagePath), dirname($imagePath), base64_decode($image['file']));
        }
        return $imagePath;
    }

    private function getRequestParameters()
    {
        return $this->restController->getRequest()->variables;
    }

    private function getRequestParameter($name)
    {
        $parameters = array_merge(
            $this->restController->getRequest()->variables,
            $this->restController->getRequest()->get
        );

        if ($name == 'embed'){
            return isset($parameters['embed']) ? explode(',', $parameters['embed']) : [];
        }

        return isset($parameters[$name]) ? $parameters[$name] : null;
    }

    private function hasRequestAcceptTypes($mediaType)
    {
        if ($this->restController->getRequest()->accept instanceof \ezcMvcRequestAccept){
            return in_array($mediaType, $this->restController->getRequest()->accept->types);
        }

        return false;
    }

    private function convertQueryInQueryParameters($query, $parameters = array(), $extraParameters = array())
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
            foreach ($parameters as $key => $value) {
                if (isset($convertedQuery[$key])) {
                    $data[$key] = is_array($convertedQuery[$key]) ? $convertedQuery[$key][0] : $convertedQuery[$key];
                }
            }

            foreach ($extraParameters as $key => $value){
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
                        $convertedQuery['filter'] = array();
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
            return array_map(array($this, 'recursiveTrim'), $value);
        } else {
            return trim($value, "'");
        }
    }

    private function cleanMessageText($text)
    {
        return strip_tags(html_entity_decode($text));
    }

}