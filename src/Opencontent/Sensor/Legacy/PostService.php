<?php

namespace Opencontent\Sensor\Legacy;

use eZCollaborationItem;
use eZContentCacheManager;
use eZContentObject;
use eZContentObjectState;
use eZPersistentObject;
use eZSearch;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Exception\DuplicateUuidException;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\PermissionException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Message\AuditCollection;
use Opencontent\Sensor\Api\Values\Message\CommentCollection;
use Opencontent\Sensor\Api\Values\Message\PrivateMessageCollection;
use Opencontent\Sensor\Api\Values\Message\TimelineItemCollection;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantCollection;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;
use Opencontent\Sensor\Core\PermissionDefinitions\CanSendPrivateMessage;
use Opencontent\Sensor\Core\PostService as PostServiceBase;
use Opencontent\Sensor\Legacy\PostService\PostBuilder;
use Opencontent\Sensor\Legacy\Utils\ExpiryTools;
use Opencontent\Sensor\Legacy\Validators\PostCreateStructValidator;
use Opencontent\Sensor\Legacy\Validators\PostUpdateStructValidator;
use Ramsey\Uuid\Uuid;

class PostService extends PostServiceBase
{
    const COLLABORATION_FIELD_OBJECT_ID = 'data_int1';

    const COLLABORATION_FIELD_LAST_CHANGE = 'data_int2';

    const COLLABORATION_FIELD_STATUS = 'data_int3';

    const COLLABORATION_FIELD_HANDLER = 'data_text1';

    const COLLABORATION_FIELD_EXPIRY = 'data_text3';

    const SITE_DATA_FIELD_PREFIX = 'sensorpost_';

    /**
     * @var Repository
     */
    protected $repository;

    public function loadPosts($query, $limit, $offset)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }
        $searchQuery = $query ? 'q = "' . $query . '"' : '';
        $result = $this->repository->getSearchService()->searchPosts("$searchQuery limit $limit cursor [$offset] sort [published=>desc]");

        return ['items' => $result->searchHits, 'next' => $result->nextCursor, 'current' => $result->currentCursor];
    }

    public function loadPost($postId)
    {
        if (!is_numeric($postId) || (int)$postId == 0) {
            throw new InvalidArgumentException("Invalid ID");
        }
        $type = $this->repository->getSensorCollaborationHandlerTypeString();
        $collaborationItem = eZPersistentObject::fetchObject(
            eZCollaborationItem::definition(),
            null,
            array(
                'type_identifier' => $type,
                self::COLLABORATION_FIELD_OBJECT_ID => intval($postId)
            )
        );
        $contentObject = eZContentObject::fetch(intval($postId));
        if (!$collaborationItem instanceof eZCollaborationItem){
            throw new NotFoundException("eZCollaborationItem $type not found for object $postId");
        }
        if (!$contentObject instanceof eZContentObject){
            throw new NotFoundException("eZContentObject not found for object $postId");
        }

        $builder = new PostBuilder($this->repository, $contentObject, $collaborationItem);
        $post = $builder->build();
        $this->refreshExpirationInfo($post);
        $this->setCommentsIsOpen($post);
        $this->setUserPostAware($post);

        return $post;
    }

    public function loadPostByUuid($postUuid)
    {
        $postUuid = \eZDB::instance()->escapeString($postUuid);
        $whereSql = "ezcontentobject.remote_id='$postUuid'";
        $fetchSQLString = "SELECT ezcontentobject.id FROM ezcontentobject WHERE $whereSql";
        $resArray = \eZDB::instance()->arrayQuery($fetchSQLString);
        if (count($resArray) == 1 && $resArray !== false) {
            return $this->loadPost($resArray[0]['id']);
        }

        throw new NotFoundException("Post not found for uuid $postUuid");
    }

    /**
     * @param $postInternalId
     * @return Post
     * @throws NotFoundException
     */
    public function loadPostByInternalId($postInternalId)
    {
        $collaborationItem = eZPersistentObject::fetchObject(
            eZCollaborationItem::definition(),
            null,
            array(
                'type_identifier' => $this->repository->getSensorCollaborationHandlerTypeString(),
                'id' => intval($postInternalId)
            )
        );
        if ($collaborationItem instanceof eZCollaborationItem) {
            $postId = $collaborationItem->attribute(self::COLLABORATION_FIELD_OBJECT_ID);
            $contentObject = eZContentObject::fetch(intval($postId));
            if ($contentObject instanceof eZContentObject) {
                $builder = new PostBuilder($this->repository, $contentObject, $collaborationItem);
                $post = $builder->build();
                $this->refreshExpirationInfo($post);
                $this->setCommentsIsOpen($post);
                $this->setUserPostAware($post);

                return $post;
            }
        }
        throw new NotFoundException("eZCollaborationItem not found for id $postInternalId");
    }

    public function refreshExpirationInfo(Post $post)
    {
        if ($post->expirationInfo->expirationDateTime instanceof \DateTime) {
            $diffResult = Utils::getDateDiff($post->expirationInfo->expirationDateTime);
            if ($diffResult->interval->invert) {
                $expirationText = \ezpI18n::tr('sensor/expiring', 'Scaduto da');
                $post->expirationInfo->label = 'danger';
            } else {
                $expirationText = \ezpI18n::tr('sensor/expiring', 'Scade fra');
                $post->expirationInfo->label = 'default';
            }
            $post->expirationInfo->text = $expirationText . ' ' . $diffResult->getText();
        }
    }

    public function setUserPostAware(Post $post)
    {
        $currentParticipant = $post->participants->getParticipantByUserId($this->repository->getCurrentUser()->id);

//        foreach ($post->participants as $participant) {
//            foreach ($participant as $user) {
//                $this->repository->getUserService()->setUserPostAware($user, $post);
//            }
//        }
        $this->repository->getUserService()->setUserPostAware(
            $this->repository->getCurrentUser(),
            $post
        );
        $userCanSendPrivateMessage = $this->repository->getPermissionService()->loadCurrentUserPostPermissionCollection($post)->hasPermission('can_send_private_message');
        $useDirectPrivateMessage = $this->repository->getSensorSettings()->get('UseDirectPrivateMessage');
        $privateMessages = new PrivateMessageCollection();
        foreach ($post->privateMessages->messages as $message) {
            $userIsSender = $message->creator->id == $this->repository->getCurrentUser()->id;
            $userIsReceiver = ($message->getReceiverById($this->repository->getCurrentUser()->id)
                || $message->getReceiverByIdList($this->repository->getCurrentUser()->groups));

            if ($userIsSender
                || ($useDirectPrivateMessage && $userIsReceiver)
                || (!$useDirectPrivateMessage && $userCanSendPrivateMessage)) {
                $privateMessages->addMessage($message);
            }
        }
        $post->privateMessages = $privateMessages;

        $userIsModerator = $this->repository->getPermissionService()->loadCurrentUserPostPermissionCollection($post)->hasPermission('can_moderate_comment');
        $comments = new CommentCollection();
        foreach ($post->comments->messages as $message){
            $userIsCreator = $message->creator->id == $this->repository->getCurrentUser()->id;
            if ((!$message->needModeration && !$message->isRejected) || $userIsCreator || $userIsModerator){
                $comments->addMessage($message);
            }
        }
        $post->comments = $comments;

        if ($this->repository->getSensorSettings()->get('HideTimelineDetails')){
            if (!$currentParticipant || $currentParticipant->roleIdentifier == ParticipantRole::ROLE_AUTHOR){
                $timelineMessages = new TimelineItemCollection();
                foreach ($post->timelineItems->messages as $message){
                    if ($message->type == 'read' || $message->type == 'closed'){
                        $timelineMessages->addMessage($message);
                    }
                }
                $post->timelineItems = $timelineMessages;
            }
        }

        if (
            $this->repository->getSensorSettings()->get('HideOperatorNames')
            && $this->repository->getCurrentUser()->type == 'user'
            && !PermissionService::isSuperAdmin($this->repository->getCurrentUser())
        ){
            $hiddenOperatorName = $this->repository->getSensorSettings()->get('HiddenOperatorName');
            $hiddenOperatorEmail = $this->repository->getSensorSettings()->get('HiddenOperatorEmail');
            $hiddenOperators = [];
            $hiddenParticipantsByRole = [];
            $hiddenParticipants = [];
            foreach ($post->participants as $participant){
                if (in_array($participant->roleIdentifier, [ParticipantRole::ROLE_OWNER, ParticipantRole::ROLE_OBSERVER])
                    && $participant->type == Participant::TYPE_USER){
                    $hiddenOperators[$participant->id] = $participant->name;
                    $hiddenParticipant = clone $participant;
                    $hiddenParticipant->name = $hiddenOperatorName;
                    $hiddenParticipant->id = 1;
                    $hiddenParticipantsByRole[$participant->roleIdentifier][$hiddenParticipant->id] = $hiddenParticipant;
                    $hiddenParticipants[] = $hiddenParticipant;
                }else{
                    $hiddenParticipantsByRole[$participant->roleIdentifier][$participant->id] = $participant;
                    $hiddenParticipants[] = $participant;
                }
            }
            $post->participants = new ParticipantCollection($hiddenParticipants);
            if (isset($hiddenParticipantsByRole[ParticipantRole::ROLE_APPROVER])) {
                $post->approvers = new Participant\ApproverCollection($hiddenParticipantsByRole[ParticipantRole::ROLE_APPROVER]);
            }
            if (isset($hiddenParticipantsByRole[ParticipantRole::ROLE_OWNER])) {
                $post->owners = new Participant\OwnerCollection($hiddenParticipantsByRole[ParticipantRole::ROLE_OWNER]);
            }
            if (isset($hiddenParticipantsByRole[ParticipantRole::ROLE_OBSERVER])) {
                $post->observers = new Participant\ObserverCollection($hiddenParticipantsByRole[ParticipantRole::ROLE_OBSERVER]);
            }
            $observerGroups = $post->observers->getParticipantsByType(Participant::TYPE_GROUP);
            if ($observerGroups->count() > 0){
                $post->observers = $observerGroups;
            }

            $timelineMessages = new TimelineItemCollection();
            foreach ($post->timelineItems->messages as $message){
                $hiddenMessage = clone $message;
                if (isset($hiddenOperators[$message->creator->id])) {
                    $hiddenMessage->creator = clone $message->creator;
                    $hiddenMessage->creator->id = 1;
                    $hiddenMessage->creator->name = $hiddenOperatorName;
                    $hiddenMessage->creator->email = $hiddenOperatorEmail;
                }
                $replaceStringList = array_fill(0, count($hiddenOperators), $hiddenOperatorName);
                $hiddenMessage->text = str_replace(array_values($hiddenOperators), $replaceStringList, $message->text);
                $hiddenMessage->richText = str_replace(array_values($hiddenOperators), $replaceStringList, $message->text);
                $timelineMessages->addMessage($hiddenMessage);
            }
            $post->timelineItems = $timelineMessages;

            $comments = new CommentCollection();
            foreach ($post->comments->messages as $message){
                $hiddenMessage = clone $message;
                if (isset($hiddenOperators[$message->creator->id])) {
                    $hiddenMessage->creator = clone $message->creator;
                    $hiddenMessage->creator->id = 1;
                    $hiddenMessage->creator->name = $hiddenOperatorName;
                    $hiddenMessage->creator->email = $hiddenOperatorEmail;
                }
                $comments->addMessage($hiddenMessage);
            }
            $post->comments = $comments;

            if ($post->latestOwner instanceof Participant){
                if (isset($hiddenOperators[$post->latestOwner->id])) {
                    $post->latestOwner = new Participant();
                    $post->latestOwner->id = 1;
                    $post->latestOwner->name = $hiddenOperatorName;
                    $post->latestOwner->email = $hiddenOperatorEmail;
                    $post->latestOwner->type = Participant::TYPE_USER;
                }
            }
        }

        if (!PermissionService::isSuperAdmin($this->repository->getCurrentUser())){
            $post->audits = new AuditCollection();
        }
    }

    public function setCommentsIsOpen(Post $post)
    {
        if ($this->repository->getSensorSettings()->has('CommentsAllowed') && !$this->repository->getSensorSettings()->get('CommentsAllowed')) {
            $post->commentsIsOpen = false;
        } else {
            $now = time();
            if ($post->resolutionInfo instanceof Post\ResolutionInfo && $this->repository->getSensorSettings()->has('CloseCommentsAfterSeconds')) {
                $time = $now - $post->resolutionInfo->resolutionDateTime->getTimestamp();
                $post->commentsIsOpen = $time < $this->repository->getSensorSettings()->get('CloseCommentsAfterSeconds');
            } else {
                $post->commentsIsOpen = true;
            }
        }
    }

    public function createPost(PostCreateStruct $post)
    {
        $validator = new PostCreateStructValidator($this->repository);
        try {
            $validator->validate($post);

            $author = $post->author ? (int)$post->author : (int)$this->repository->getCurrentUser()->id;
            $reporter = $author != (int)$this->repository->getCurrentUser()->id ? (int)$this->repository->getCurrentUser()->id : null;
            $class = $this->repository->getPostContentClass();

            $privacyAttributeType = isset($class->dataMap()['privacy']) ? $class->dataMap()['privacy']->attribute('data_type_string') : \eZBooleanType::DATA_TYPE_STRING;
            if ($privacyAttributeType == \eZSelectionType::DATA_TYPE_STRING) {
                $privacy = $post->privacy === 'public' ? 'Si' : 'No';
            } else {
                $privacy = $post->privacy === 'public' ? '1' : '0';
            }

            $params = [
                'creator_id' => $author,
                'class_identifier' => $class->attribute('identifier'),
                'parent_node_id' => (int)$this->repository->getPostRootNode()->attribute('node_id'),
                'attributes' => [
                    'subject' => (string)$post->subject,
                    'description' => (string)$post->description,
                    'type' => (string)$post->type,
                    'geo' => (string)$post->geoLocation,
                    'privacy' => $privacy,
                    'meta' => (string)$post->meta,
                    'reporter' => $reporter,
                    'on_behalf_of' => $reporter ? $author : '',
                    'on_behalf_of_mode' => (string)$post->channel,
                ]
            ];
            if (!empty($post->imagePath)) {
                $params['attributes']['image'] = $post->imagePath;
            }
            if (count($post->imagePaths) > 0) {
                $params['attributes']['images'] = implode('|', $post->imagePaths);
            }
            if (count($post->areas) > 0) {
                $params['attributes']['area'] = implode('|', $post->areas);
            }
            if (count($post->categories) > 0) {
                $params['attributes']['category'] = implode('|', $post->categories);
            }
            $remoteId = !empty($post->uuid) ? $post->uuid : Uuid::uuid4();
            $params['remote_id'] = $remoteId;

            $object = \eZContentFunctions::createAndPublishObject($params);

            return $this->loadPost($object->attribute('id'));

        }catch (DuplicateUuidException $e){

            $existingPost = $e->getPost();
            if (isset($existingPost->meta['pingback_url'])){
                if ($existingPost->workflowStatus->is(Post\WorkflowStatus::CLOSED)){
                    $this->repository->getActionService()->runAction(
                        new Action('reopen', [], true),
                        $existingPost
                    );
                }

                return $this->loadPost($existingPost->id);
            }

            throw $e;
        }
    }

    public function updatePost(PostUpdateStruct $post)
    {
        $contentObject = \eZContentObject::fetch((int)$post->getPost()->id);
        $validator = new PostUpdateStructValidator($this->repository, $contentObject);
        $validator->validate($post);

        $attributes = array();
        foreach (['subject', 'description', 'type', 'geo', 'meta'] as $identifier) {
            if (!empty((string)$post->{$identifier})) {
                $attributes[$identifier] = (string)$post->{$identifier};
            }
        }
        if (!empty((string)$post->areas)) {
            $attributes['area'] = implode('-', $post->areas);
        }
        if (!empty((string)$post->categories)) {
            $attributes['category'] = implode('-', $post->categories);
        }
        if ((string)$post->privacy != '') {
            $attributes['privacy'] = (string)$post->privacy == 'public';
        }
        if (!empty($post->imagePaths)) {
            $attributes['images'] = implode('|', $post->imagePaths);
        }

        if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {
            return $this->loadPost($contentObject->attribute('id'));
        }

        throw new UnexpectedException("Update failed");
    }

    public function deletePost(Post $post)
    {
        if (!$this->repository->getCurrentUser()->permissions->hasPermission('can_remove')) {
            throw new PermissionException('can_remove', $this->repository->getCurrentUser(), $post);
        }

        $moveToTrash = false;
        $deleteIDArray = array();
        foreach ($this->getContentObject($post)->assignedNodes() as $node) {
            $deleteIDArray[] = $node->attribute('node_id');
        }
        if (!empty($deleteIDArray)) {
            if (\eZOperationHandler::operationIsAvailable('content_delete')) {
                \eZOperationHandler::execute('content',
                    'delete',
                    array(
                        'node_id_list' => $deleteIDArray,
                        'move_to_trash' => $moveToTrash
                    ),
                    null, true);
            } else {
                \eZContentOperationCollection::deleteObject($deleteIDArray, $moveToTrash);
            }
        }

        return true;
    }

    public function trashPost(Post $post)
    {
        $participants = $post->participants->getParticipantIdList();
        foreach( $participants as $participantId )
        {
            $this->repository->getParticipantService()->trashPostParticipant($post, $participantId);
        }
    }

    public function restorePost(Post $post)
    {
        $participants = $post->participants->getParticipantIdList();
        foreach( $participants as $participantId )
        {
            $this->repository->getParticipantService()->restorePostParticipant($post, $participantId);
        }
    }

    public function getContentObject(Post $post)
    {
        $contentObject = eZContentObject::fetch(intval($post->id));
        if (!$contentObject instanceof eZContentObject) {
            throw new NotFoundException("eZContentObject not found for id {$post->id}");
        }

        return $contentObject;
    }

    public function getCollaborationItem(Post $post)
    {
        $collaborationItem = eZPersistentObject::fetchObject(
            eZCollaborationItem::definition(),
            null,
            array(
                'type_identifier' => $this->repository->getSensorCollaborationHandlerTypeString(),
                'id' => intval($post->internalId)
            )
        );
        if (!$collaborationItem instanceof eZCollaborationItem) {
            throw new NotFoundException("eZCollaborationItem not found for id {$post->internalId}");
        }

        return $collaborationItem;
    }

    public function refreshPost(Post $post, $modifyTimestamp = true)
    {
        $this->repository->getLogger()->debug($modifyTimestamp ? 'Hard refresh post #' . $post->id  : 'Refresh post #' . $post->id);
        eZContentObject::clearCache($post->id);
        $timestamp = time();
        $contentObject = $this->getContentObject($post);
        if ($modifyTimestamp) {
            $contentObject->setAttribute('modified', $timestamp);
            $contentObject->store();
        }
        if ($contentObject->mainNodeID()) {
            eZContentCacheManager::clearContentCacheIfNeeded($contentObject->attribute('id'));
            eZSearch::addObject($contentObject, true);
            return $this->loadPost($post->id);
        }

        return $post;
    }

    public function setPostStatus(Post $post, $status)
    {
        $contentObject = $this->getContentObject($post);
        if (!$status instanceof eZContentObjectState) {
            list($group, $identifier) = explode('.', $status);
            $states = $this->repository->getSensorPostStates($group);
            $status = $states["{$group}.{$identifier}"];
        }

        if ($status instanceof eZContentObjectState) {
            $contentObject->assignState($status);
        }
    }

    public function setPostWorkflowStatus(Post $post, $status)
    {
        $states = $this->repository->getSensorPostStates('sensor');
        $collaborationItem = $this->getCollaborationItem($post);

        $timestamp = time();

        $collaborationItem->setAttribute(self::COLLABORATION_FIELD_STATUS, $status);
        $collaborationItem->setAttribute('modified', $timestamp);
        $collaborationItem->setAttribute(self::COLLABORATION_FIELD_LAST_CHANGE, $timestamp);

        if ($status == Post\WorkflowStatus::READ || $status == Post\WorkflowStatus::ASSIGNED) {
            $this->setPostStatus($post, $states['sensor.open']);
        } elseif ($status == Post\WorkflowStatus::CLOSED) {
            $collaborationItem->setAttribute('status', eZCollaborationItem::STATUS_INACTIVE);
            $this->repository->getParticipantService()->deactivatePostParticipants($post);
            $this->setPostStatus($post, $states['sensor.close']);
        } elseif ($status == Post\WorkflowStatus::WAITING) {
            $collaborationItem->setAttribute('status', eZCollaborationItem::STATUS_ACTIVE);
            $this->repository->getParticipantService()->activatePostParticipants($post);
        } elseif ($status == Post\WorkflowStatus::REOPENED) {
            $collaborationItem->setAttribute('status', eZCollaborationItem::STATUS_ACTIVE);
            $this->repository->getParticipantService()->activatePostParticipants($post);
            $this->setPostStatus($post, $states['sensor.pending']);
        }
        $collaborationItem->sync();
    }

    public function setPostExpirationInfo(Post $post, $expiryDays)
    {
        $collaborationItem = $this->getCollaborationItem($post);
        $collaborationItem->setAttribute(
            self::COLLABORATION_FIELD_EXPIRY,
            ExpiryTools::addDaysToTimestamp($collaborationItem->attribute('created'), $expiryDays)
        );
        $collaborationItem->store();
    }

    public function setPostCategory(Post $post, $category)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['category'])) {
            $contentObjectDataMap['category']->fromString($category);
            $contentObjectDataMap['category']->store();
        }
    }

    public function setPostArea(Post $post, $area)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['area'])) {
            $contentObjectDataMap['area']->fromString($area);
            $contentObjectDataMap['area']->store();
        }
    }

    public function addAttachment(Post $post, $files)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['attachment'])
            && ($contentObjectDataMap['attachment']->attribute('data_type_string') == \eZBinaryFileType::DATA_TYPE_STRING
                || (class_exists('\OCMultiBinaryType') && $contentObjectDataMap['attachment']->attribute('data_type_string') == \OCMultiBinaryType::DATA_TYPE_STRING))
        ) {
            $attribute = $contentObjectDataMap['attachment'];
            foreach ($files as $file) {
                $tempFilePath = \eZSys::cacheDirectory() . '/fileupload/' . $file['filename'];
                \eZFile::create(basename($tempFilePath), dirname($tempFilePath), base64_decode($file['file']));
                $attribute->dataType()->insertRegularFile(
                    $attribute->attribute('object'),
                    $attribute->attribute('version'),
                    $attribute->attribute('language_code'),
                    $attribute,
                    $tempFilePath,
                    $response
                );
                @unlink($tempFilePath);
            }
        }
    }

    public function removeAttachment(Post $post, $files)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['attachment'])) {
            if (class_exists('\OCMultiBinaryType') && $contentObjectDataMap['attachment']->attribute('data_type_string') == \OCMultiBinaryType::DATA_TYPE_STRING) {
                /** @var \eZMultiBinaryFile[] $currentFiles */
                $currentFiles = $contentObjectDataMap['attachment']->content();
                foreach ($files as $file) {
                    $filename = false;
                    foreach ($currentFiles as $currentFile) {
                        if ($currentFile->attribute('original_filename') == $file) {
                            $filename = $currentFile->attribute('filename');
                            break;
                        }
                    }
                    if ($filename) {
                        $http = new \eZHTTPTool();
                        $postValue = [];
                        $postValue[$contentObjectDataMap['attachment']->attribute('id') . '_delete_multibinary'][$filename] = 1;
                        $http->setPostVariable('CustomActionButton', $postValue);
                        $contentObjectDataMap['attachment']->customHTTPAction($http, 'delete_multibinary', []);
                    }
                }
            } elseif ($contentObjectDataMap['attachment']->attribute('data_type_string') == \eZBinaryFileType::DATA_TYPE_STRING) {
                $http = new \eZHTTPTool();
                $contentObjectDataMap['attachment']->customHTTPAction($http, 'delete_binary', []);
            }
        }

    }

    public function addImage(Post $post, $files)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['images'])
            && $contentObjectDataMap['images']->attribute('data_type_string') == 'ocmultibinary'){
            $attribute = $contentObjectDataMap['images'];
            foreach ($files as $file) {
                $tempFilePath = \eZSys::cacheDirectory() . '/fileupload/' . $file['filename'];
                \eZFile::create(basename($tempFilePath), dirname($tempFilePath), base64_decode($file['file']));
                $attribute->dataType()->insertRegularFile(
                    $attribute->attribute('object'),
                    $attribute->attribute('version'),
                    $attribute->attribute('language_code'),
                    $attribute,
                    $tempFilePath,
                    $response
                );
                @unlink($tempFilePath);
            }
        }
    }

    public function removeImage(Post $post, $files)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['images'])) {
            if ($contentObjectDataMap['images']->attribute('data_type_string') == 'ocmultibinary') {
                /** @var \eZMultiBinaryFile[] $currentFiles */
                $currentFiles = $contentObjectDataMap['images']->content();
                foreach ($files as $file) {
                    $filename = false;
                    //echo '<pre>';print_r($currentFiles);die();
                    foreach ($currentFiles as $currentFile) {
                        if ($currentFile->attribute('original_filename') == basename($file)) {
                            $filename = $currentFile->attribute('filename');
                            break;
                        }
                    }
                    if ($filename) {
                        $http = new \eZHTTPTool();
                        $postValue = [];
                        $postValue[$contentObjectDataMap['images']->attribute('id') . '_delete_multibinary'][$filename] = 1;
                        $http->setPostVariable('CustomActionButton', $postValue);
                        $contentObjectDataMap['images']->customHTTPAction($http, 'delete_multibinary', []);
                    }
                }
            }
        }
    }

    public function setPostType(Post $post, Post\Type $type)
    {
        $contentObjectDataMap = $this->getContentObject($post)->dataMap();
        if (isset($contentObjectDataMap['type'])) {
            $contentObjectDataMap['type']->fromString($type->identifier);
            $contentObjectDataMap['type']->store();
        }
    }
}
