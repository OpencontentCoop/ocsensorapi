<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;
use Opencontent\Sensor\Api\Values\Post\Field\Image;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;
use Opencontent\Sensor\OpenApi;
use SensorOpenApiController;
use ezpRestMvcResult;
use Opencontent\Sensor\Api\SearchService;
use Opencontent\Sensor\Legacy\SearchService\QueryBuilder;
use Opencontent\QueryLanguage\Parser\Item;
use Opencontent\QueryLanguage\Parser\Parameter;

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
     * @var SensorOpenApiController
     */
    private $restController;

    /**
     * @var AbstractSerializer[]
     */
    private $serializer;

    public function __construct(OpenApi $apiSettings, SensorOpenApiController $restController)
    {
        $this->apiSettings = $apiSettings;
        $this->repository = $this->apiSettings->getRepository();
        $this->restController = $restController;
        $this->serializer = [
            'post' => new PostSerializer($this->apiSettings),
            'message' => new MessageSerializer($this->apiSettings),
            'attachment' => new AttachmentSerializer($this->apiSettings),
            'area' => new AreaSerializer($this->apiSettings),
            'participants' => new ParticipantsSerializer($this->apiSettings),
            'user' => new UserSerializer($this->apiSettings),
            'operator' => new OperatorSerializer($this->apiSettings),
        ];
    }

    public function loadPosts()
    {
        $q = $this->getRequestParameter('q');
        $limit = $this->getRequestParameter('limit');
        $offset = $this->getRequestParameter('offset');
        $cursor = $this->getRequestParameter('cursor');

        if ($limit > SearchService::MAX_LIMIT) {
            throw new InvalidArgumentException('Max limit allowed is ' . SearchService::MAX_LIMIT);
        }

        $query = '';
        if ($q) {
            $query = 'q = "' . $q . '" ';
        }
        if ($offset !== null) {
            $query .= "limit $limit offset $offset sort [id=>asc]";
        } else {
            $query .= "limit $limit cursor [$cursor] sort [id=>asc]";
        }

        $searchResults = $this->repository->getSearchService()->searchPosts($query);
        $postSearchResults = [
            'self' => $this->restController->getBaseUri() . "/posts?" . $this->convertQueryInQueryParameters($searchResults->query, $this->getRequestParameters()),
            'next' => null,
            'items' => $this->serializer['post']->serializeItems($searchResults->searchHits)
        ];
        if ($searchResults->nextPageQuery) {
            $postSearchResults['next'] = $this->restController->getBaseUri() . "/posts?" . $this->convertQueryInQueryParameters($searchResults->nextPageQuery, $this->getRequestParameters());
        }

        $result = new ezpRestMvcResult();
        $result->variables = $postSearchResults;

        return $result;
    }

    public function createPost()
    {
        $postCreateStruct = $this->loadPostCreateStruct();

        $post = $this->repository->getPostService()->createPost($postCreateStruct);
        if ($postCreateStruct->imagePath) @unlink($postCreateStruct->imagePath);

        $result = new ezpRestMvcResult();
        $result->status = new \ezpRestStatusResponse(201, $this->serializer['post']->serialize($post));

        return $result;
    }

    public function getPostById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['post']->serializeItem($this->loadPost());

        return $result;
    }

    public function updatePostById()
    {
        $postUpdateStruct = $this->loadPostUpdateStruct();
        $postUpdateStruct->setPost($this->loadPost());
        $post = $this->repository->getPostService()->updatePost($postUpdateStruct);
        if ($postUpdateStruct->imagePath) @unlink($postUpdateStruct->imagePath);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['post']->serialize($post);

        return $result;
    }

    public function getApproversByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->approvers);
        return $result;
    }

    public function setApproversToPostId()
    {
        $action = new Action();
        $action->identifier = 'add_approver';
        $action->setParameter('participant_ids', $this->restController->getPayload()['participant_ids']);
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->approvers);

        return $result;
    }

    public function getOwnersByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->owners);

        return $result;
    }

    public function setOwnersToPostId()
    {
        $action = new Action();
        $action->identifier = 'assign';
        $action->setParameter('participant_ids', $this->restController->getPayload()['participant_ids']);
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->owners);

        return $result;
    }

    public function getObserversByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->observers);

        return $result;
    }

    public function setObserversToPostId()
    {
        $action = new Action();
        $action->identifier = 'add_observer';
        $action->setParameter('participant_ids', $this->restController->getPayload()['participant_ids']);
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->observers);

        return $result;
    }

    public function getParticipantsByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['participants']->serialize($this->loadPost()->participants);

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
        $result->variables = ['items' => $this->serializer['message']->serializeItems($this->loadPost()->comments)];

        return $result;
    }

    public function addCommentsToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'add_comment';
        $action->setParameter('text', $this->restController->getPayload()['text']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->status = new \ezpRestStatusResponse(
            201,
            $this->serializer['message']->serialize($this->repository->getMessageService()->loadCommentCollectionByPost($post)->last())
        );

        return $result;
    }

    public function editCommentsInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'edit_comment';
        $action->setParameter('id', $this->restController->commentId);
        $action->setParameter('text', $this->restController->getPayload()['text']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['message']->serialize($this->repository->getMessageService()->loadCommentCollectionByPost($post)->getById($this->restController->commentId));

        return $result;
    }

    public function getPrivateMessagesByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer['message']->serializeItems($this->loadPost()->privateMessages)];

        return $result;
    }

    public function addPrivateMessageToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'send_private_message';
        $action->setParameter('participant_ids', $this->restController->getPayload()['receivers']);
        $action->setParameter('text', $this->restController->getPayload()['text']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->status = new \ezpRestStatusResponse(
            201,
            $this->serializer['message']->serialize($this->repository->getMessageService()->loadCommentCollectionByPost($post)->last())
        );

        return $result;
    }

    public function editPrivateMessageInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'edit_message';
        $action->setParameter('id', $this->restController->privateMessageId);
        $action->setParameter('text', $this->restController->getPayload()['text']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['message']->serialize($this->repository->getMessageService()->loadPrivateMessageCollectionByPost($post)->getById($this->restController->privateMessageId));

        return $result;
    }

    public function getResponsesByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer['message']->serializeItems($this->loadPost()->responses)];

        return $result;
    }

    public function addResponsesToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'add_response';
        $action->setParameter('text', $this->restController->getPayload()['text']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->status = new \ezpRestStatusResponse(
            201,
            $this->serializer['message']->serialize($this->repository->getMessageService()->loadResponseCollectionByPost($post)->last())
        );

        return $result;
    }

    public function editResponsesInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'edit_response';
        $action->setParameter('id', $this->restController->responseId);
        $action->setParameter('text', $this->restController->getPayload()['text']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['message']->serialize($this->repository->getMessageService()->loadResponseCollectionByPost($post)->getById($this->restController->responseId));

        return $result;
    }

    public function getAttachmentsByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer['attachment']->serializeItems($this->loadPost()->attachments)];

        return $result;
    }

    public function addAttachmentsToPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'add_attachment';
        $action->setParameter('files', $this->restController->getPayload()['files']);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->status = new \ezpRestStatusResponse(
            201,
            ['items' => $this->serializer['attachment']->serializeItems($this->loadPost()->attachments)]
        );

        return $result;
    }

    public function deleteAttachmentsInPostId()
    {
        $post = $this->loadPost();
        $action = new Action();
        $action->identifier = 'remove_attachment';
        $action->setParameter('files', [$this->restController->filename]);
        $this->repository->getActionService()->runAction($action, $post);

        $result = new ezpRestMvcResult();
        $result->status = new \ezpRestStatusResponse(
            204,
            \ezpRestStatusResponse::$statusCodes[204]
        );

        return $result;
    }

    public function getTimelineByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = ['items' => $this->serializer['message']->serializeItems($this->loadPost()->timelineItems)];

        return $result;
    }

    public function getAreasByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = [
            'items' => $this->serializer['area']->serializeItems($this->loadPost()->areas),
            'self' => $this->restController->getBaseUri() . "/post/{$this->restController->postId}/areas",
            'next' => null
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
        $result->variables = ['items' => $this->serializer['area']->serializeItems($this->loadPost()->areas)];

        return $result;
    }

    public function getCategoriesByPostId()
    {
        $result = new ezpRestMvcResult();
        $result->variables = [
            'items' => $this->serializer['area']->serializeItems($this->loadPost()->categories),
            'self' => $this->restController->getBaseUri() . "/post/{$this->restController->postId}/categories",
            'next' => null
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
        $result->variables = ['items' => $this->serializer['area']->serializeItems($this->loadPost()->categories)];

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
        $newStatus = $this->restController->getPayload()['identifier'];


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

        return $result;
    }

    public function setExpiryByPostId()
    {
        $action = new Action();
        $action->identifier = 'set_expiry';
        $action->setParameter('expiry_days', (int)$this->restController->getPayload());
        $this->repository->getActionService()->runAction($action, $this->loadPost());

        $result = new ezpRestMvcResult();
        $result->variables = ['expiry_at' => $this->serializer['post']->serialize($this->loadPost())['expiry_at']];
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
        $results = [
            'self' => $this->restController->getBaseUri() . "/users?limit=$limit&cursor=" . urlencode($searchResults['current']),
            'next' => null,
            'items' => $this->serializer['user']->serializeItems($searchResults['items'])
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri() . "/users?limit=$limit&cursor=" . urlencode($searchResults['next']);
        }

        $result = new ezpRestMvcResult();
        $result->variables = $results;

        return $result;
    }

    public function createUser()
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
        if (!\eZMail::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (\eZUser::fetchByEmail($payload['email'])) {
            throw new InvalidInputException("Email address already exists");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['user']->serializeItem($this->repository->getUserService()->createUser($payload));
        return $result;
    }

    public function getUserById()
    {
        $result = new ezpRestMvcResult();
        // utilizzo la ricerca per il controllo dei permessi di accesso
        $userData = $this->repository->getUserService()->searchOne('id = ' . $this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);
        $result->variables = $this->serializer['user']->serializeItem($user);

        return $result;
    }

    public function updateUserById()
    {
        $userData = $this->repository->getUserService()->searchOne('id = ' . $this->restController->userId);
        $user = $this->repository->getUserService()->loadUser($userData['metadata']['id']);

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
        if (!\eZMail::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (\eZUser::fetchByEmail($payload['email']) && $user->email != $payload['email']) {
            throw new InvalidInputException("Email address already exists");
        }

        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['user']->serializeItem($this->repository->getUserService()->updateUser($user, $payload));
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
        $results = [
            'self' => $this->restController->getBaseUri() . "/operators?limit=$limit&cursor=" . urlencode($searchResults['current']),
            'next' => null,
            'items' => $this->serializer['operator']->serializeItems($searchResults['items'])
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri() . "/operators?limit=$limit&cursor=" . urlencode($searchResults['next']);
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
        if (!\eZMail::validate($payload['email'])) {
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
        $result->variables = $this->serializer['operator']->serializeItem($this->repository->getOperatorService()->createOperator($payload));
        return $result;
    }

    public function getOperatorById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['operator']->serializeItem($this->repository->getOperatorService()->loadOperator($this->restController->operatorId));

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
        if (!\eZMail::validate($payload['email'])) {
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
        $result->variables = $this->serializer['user']->serializeItem($this->repository->getOperatorService()->updateOperator($operator, $payload));
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
        $results = [
            'self' => $this->restController->getBaseUri() . "//groups?limit=$limit&cursor=" . urlencode($searchResults['current']),
            'next' => null,
            'items' => $searchResults['items']
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri() . "//groups?limit=$limit&cursor=" . urlencode($searchResults['next']);
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
        if (!\eZMail::validate($payload['email'])) {
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
            'items' => $searchResults['items']
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
        if (!\eZMail::validate($payload['email'])) {
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
        $results = [
            'self' => $this->restController->getBaseUri() . "/categories?limit=$limit&cursor=" . urlencode($searchResults['current']),
            'next' => null,
            'items' => $this->serializer['area']->serializeItems($searchResults['items'])
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri() . "/categories?limit=$limit&cursor=" . urlencode($searchResults['next']);
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
        if (!empty($payload['operators'])) {
            foreach ($payload['operators'] as $operator) {
                $this->repository->getOperatorService()->loadOperator($operator);
            }
        }
        if (!empty($payload['groups'])) {
            foreach ($payload['groups'] as $group) {
                $this->repository->getGroupService()->loadGroup($group);
            }
        }
        if (!empty($payload['parent'])) {
            $this->repository->getCategoryService()->loadCategory($payload['parent']);
        }


        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['area']->serialize($this->repository->getCategoryService()->createCategory($payload));
        return $result;
    }

    public function getCategoryById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['area']->serialize($this->repository->getCategoryService()->loadCategory($this->restController->categoryId));

        return $result;
    }

    public function updateCategoryById()
    {
        $category = $this->repository->getCategoryService()->loadCategory($this->restController->categoryId);

        $payload = $this->restController->getPayload();
        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (!empty($payload['operators'])) {
            foreach ($payload['operators'] as $operator) {
                $this->repository->getOperatorService()->loadOperator($operator);
            }
        }
        if (!empty($payload['groups'])) {
            foreach ($payload['groups'] as $group) {
                $this->repository->getGroupService()->loadGroup($group);
            }
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
        $results = [
            'self' => $this->restController->getBaseUri() . "/areas?limit=$limit&cursor=" . urlencode($searchResults['current']),
            'next' => null,
            'items' => $this->serializer['area']->serializeItems($searchResults['items'])
        ];
        if ($searchResults['next']) {
            $results['next'] = $this->restController->getBaseUri() . "/areas?limit=$limit&cursor=" . urlencode($searchResults['next']);
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
        if (!empty($payload['operators'])) {
            foreach ($payload['operators'] as $operator) {
                $this->repository->getOperatorService()->loadOperator($operator);
            }
        }
        if (!empty($payload['groups'])) {
            foreach ($payload['groups'] as $group) {
                $this->repository->getGroupService()->loadGroup($group);
            }
        }


        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['area']->serialize($this->repository->getAreaService()->createArea($payload));
        return $result;
    }

    public function getAreaById()
    {
        $result = new ezpRestMvcResult();
        $result->variables = $this->serializer['area']->serialize($this->repository->getAreaService()->loadArea($this->restController->areaId));
        return $result;
    }

    public function updateAreaById()
    {
        $area = $this->repository->getAreaService()->loadArea($this->restController->areaId);

        $payload = $this->restController->getPayload();
        if (empty($payload['name'])) {
            throw new InvalidInputException("Field name is required");
        }
        if (!empty($payload['operators'])) {
            foreach ($payload['operators'] as $operator) {
                $this->repository->getOperatorService()->loadOperator($operator);
            }
        }
        if (!empty($payload['groups'])) {
            foreach ($payload['groups'] as $group) {
                $this->repository->getGroupService()->loadGroup($group);
            }
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

    private function loadPost()
    {
        return $this->repository->getSearchService()->searchPost($this->restController->postId);
    }

    /**
     * @return PostUpdateStruct
     * @throws InvalidInputException
     */
    private function loadPostUpdateStruct()
    {
        $postStruct = new PostUpdateStruct();
        return $this->loadPostStruct($postStruct);
    }

    /**
     * @return PostCreateStruct
     * @throws InvalidInputException
     */
    private function loadPostCreateStruct()
    {
        $postStruct = new PostCreateStruct();
        return $this->loadPostStruct($postStruct);
    }

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

        if (isset($payload['address'])) {
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
        }

        if (isset($payload['type'])) {
            $postCreateStruct->type = $payload['type'];
        }

        $postCreateStruct->privacy = isset($payload['is_private']) && $payload['is_private'] ? 'private' : 'public';

        if (isset($payload['image'])) {
            if (empty($payload['image']['filename']) || empty($payload['image']['file'])) {
                throw new InvalidInputException("Field image has wrong format");
            }
            $imagePath = \eZSys::cacheDirectory() . '/' . $payload['image']['filename'];
            \eZFile::create(basename($imagePath), dirname($imagePath), base64_decode($payload['image']['file']));
            $postCreateStruct->imagePath = $imagePath;
        }

        return $postCreateStruct;
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
        return isset($parameters[$name]) ? $parameters[$name] : null;
    }

    private function convertQueryInQueryParameters($query, $parameters = array())
    {
        try {
            $queryBuilder = new QueryBuilder($this->repository->getPostContentClassIdentifier());
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

}