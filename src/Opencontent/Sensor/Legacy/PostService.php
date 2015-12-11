<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Api\Values\Message\PrivateMessageCollection;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Core\PostService as PostServiceBase;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\PostCreateStruct;
use OpenContent\Sensor\Api\Values\PostUpdateStruct;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Exception\BaseException;
use eZPersistentObject;
use eZCollaborationItem;
use eZContentObject;
use eZContentObjectAttribute;
use eZContentObjectState;
use eZImageAliasHandler;
use DateInterval;
use ezpI18n;
use OpenContent\Sensor\Api\Values\Message\TimelineItemStruct;
use eZContentCacheManager;
use eZSearch;
use OpenContent\Sensor\Utils\ExpiryTools;

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

    /**
     * @var eZContentObject
     */
    protected $contentObject;

    /**
     * @var eZContentObjectAttribute[]
     */
    protected $contentObjectDataMap;

    /**
     * @var eZCollaborationItem
     */
    protected $collaborationItem;

    public function loadPost( $postId )
    {
        $type = $this->repository->getSensorCollaborationHandlerTypeString();
        $this->collaborationItem = eZPersistentObject::fetchObject(
            eZCollaborationItem::definition(),
            null,
            array(
                'type_identifier' => $type,
                'data_int1' => intval( $postId )
            )
        );
        $this->getContentObject( $postId );
        if ( $this->collaborationItem instanceof eZCollaborationItem && $this->contentObject instanceof eZContentObject )
        {
            $this->getContentObjectDataMap( $postId );
            return $this->internalLoadPost();
        }
        throw new BaseException( "eZCollaborationItem $type not found for object $postId" );
    }

    public function loadPostByInternalId( $postInternalId )
    {
        $this->getCollaborationItem( $postInternalId );
        if ( $this->collaborationItem instanceof eZCollaborationItem )
        {
            $postId = $this->collaborationItem->attribute( 'data_int1' );
            $this->getContentObject( $postId );
            if ( $this->contentObject instanceof eZContentObject )
            {
                $this->getContentObjectDataMap( $postId );
                return $this->internalLoadPost();
            }
        }
        throw new BaseException( "eZCollaborationItem not found for id $postInternalId" );
    }

    protected function getContentObject( $postId )
    {
        if ( $this->contentObject === null )
        {
            $this->contentObject = eZContentObject::fetch( intval( $postId ) );
        }
        return $this->contentObject;
    }

    protected function getContentObjectDataMap( $postId )
    {
        if ( $this->contentObjectDataMap === null )
        {
            $this->getContentObject( $postId );
            if ( !$this->contentObject instanceof eZContentObject )
            {
                throw new BaseException( "eZContentObject not found for id {$postId}" );
            }
            $this->contentObjectDataMap = $this->contentObject->fetchDataMap(
                false,
                $this->repository->getCurrentLanguage()
            );
        }
        return $this->contentObjectDataMap;
    }

    protected function getCollaborationItem( $postInternalId )
    {
        if ( $this->collaborationItem === null )
        {
            $type = $this->repository->getSensorCollaborationHandlerTypeString();
            $this->collaborationItem = eZPersistentObject::fetchObject(
                eZCollaborationItem::definition(),
                null,
                array(
                    'type_identifier' => $type,
                    'id' => intval( $postInternalId )
                )
            );
        }

        return $this->collaborationItem;
    }

    protected function internalLoadPost()
    {
        $post = new Post();
        $post->id = $this->contentObject->attribute( 'id' );
        $post->internalId = $this->collaborationItem->attribute( 'id' );

        $post->published = Utils::getDateTimeFromTimestamp(
            $this->contentObject->attribute( 'published' )
        );
        $post->modified = Utils::getDateTimeFromTimestamp(
            $this->contentObject->attribute( 'modified' )
        );;

        $post->expirationInfo = $this->getPostExpirationInfo();
        $post->resolutionInfo = $this->getPostResolutionInfo( $post );

        $post->privacy = $this->getPostPrivacyCurrentStatus();
        $post->status = $this->getPostCurrentStatus();
        $post->moderation = $this->getPostModerationCurrentStatus();
        $post->workflowStatus = $this->getPostWorkflowStatus();

        $post->subject = $this->contentObject->name(
            false,
            $this->repository->getCurrentLanguage()
        );
        $post->description = $this->getPostDescription();
        $post->type = $this->getPostType();
        $post->images = $this->getPostImages();
        $post->attachments = $this->getPostAttachments();
        $post->categories = $this->getPostCategories();
        $post->areas = $this->getPostAreas();
        $post->geoLocation = $this->getPostGeoLocation();

        $post->participants = $this->repository->getParticipantService()->loadPostParticipants(
            $post
        );
        $authors = $this->repository->getParticipantService()->loadPostParticipantsByRole(
            $post,
            ParticipantRole::ROLE_AUTHOR
        );
        $post->reporter = $authors->first();
        $post->approvers = Participant\ApproverCollection::fromCollection(
            $this->repository->getParticipantService()->loadPostParticipantsByRole(
                $post,
                ParticipantRole::ROLE_APPROVER
            )
        );
        $post->owners = Participant\OwnerCollection::fromCollection(
            $this->repository->getParticipantService()->loadPostParticipantsByRole(
                $post,
                ParticipantRole::ROLE_OWNER
            )
        );
        $post->observers = Participant\ObserverCollection::fromCollection(
            $this->repository->getParticipantService()->loadPostParticipantsByRole(
                $post,
                ParticipantRole::ROLE_OBSERVER
            )
        );

        $post->author = clone $post->reporter;
        $authorName = $this->getPostAuthorName();
        if ( $authorName )
        {
            $post->author->name = $authorName;
        }

        $post->comments = $this->repository->getMessageService()->loadCommentCollectionByPost( $post );
        $post->privateMessages = $this->repository->getMessageService()->loadPrivateMessageCollectionByPost( $post );
        $post->timelineItems = $this->repository->getMessageService()->loadTimelineItemCollectionByPost( $post );
        $post->responses = $this->repository->getMessageService()->loadResponseCollectionByPost( $post );

        $post->commentsIsOpen = $this->getCommentsIsOpen( $post );

        $this->setUserPostAware( $post );

        $post->internalStatus = 'miss';

        return $post;
    }

    public function setUserPostAware( Post $post )
    {
        foreach ( $post->participants as $participant )
        {
            foreach ( $participant as $user )
            {
                $this->repository->getUserService()->setUserPostAware( $user, $post );
            }
        }
        $this->repository->getUserService()->setUserPostAware(
            $this->repository->getCurrentUser(),
            $post
        );

        $messageUserPostAware = new PrivateMessageCollection();
        foreach( $post->privateMessages->messages as $message )
        {
            if ( $message->getReceiverById( $this->repository->getCurrentUser()->id )
                 || $message->creator->id == $this->repository->getCurrentUser()->id )
            {
                $messageUserPostAware->addMessage( $message );
            }
        }
        $post->privateMessages = $messageUserPostAware;
    }

    protected function getCommentsIsOpen( Post $post )
    {
        $now = time();
        $resolutionInfo = $this->getPostResolutionInfo( $post );
        if ( $resolutionInfo instanceof Post\ResolutionInfo
             && $this->repository->getSensorSettings()->has( 'CloseCommentsAfterSeconds' )
        )
        {
            $time = $now - $resolutionInfo->resolutionDateTime->getTimestamp();
            return $time < $this->repository->getSensorSettings()->get( 'CloseCommentsAfterSeconds' );
        }

        return true;
    }

    protected function getPostAuthorName()
    {
        $authorName = false;
        if ( isset( $this->contentObjectDataMap['on_behalf_of'] )
             && $this->contentObjectDataMap['on_behalf_of']->hasContent()
        )
        {
            $authorName = $this->contentObjectDataMap['on_behalf_of']->toString();
            if ( isset( $this->contentObjectDataMap['on_behalf_of_detail'] )
                 && $this->contentObjectDataMap['on_behalf_of_detail']->hasContent()
            )
            {
                $authorName .= ', ' . $this->contentObjectDataMap['on_behalf_of_detail']->toString();
            }
        }

        return $authorName;
    }

    protected function getPostWorkflowStatus()
    {
        return Post\WorkflowStatus::instanceByCode(
            $this->collaborationItem->attribute( self::COLLABORATION_FIELD_STATUS )
        );
    }

    protected function getPostExpirationInfo()
    {
        $publishedDateTime = Utils::getDateTimeFromTimestamp(
            $this->contentObject->attribute( 'published' )
        );
        $expirationDateTime = Utils::getDateTimeFromTimestamp(
            intval( $this->collaborationItem->attribute( self::COLLABORATION_FIELD_EXPIRY ) )
        );

        $diffResult = Utils::getDateDiff( $expirationDateTime );
        if ( $diffResult->interval->invert )
        {
            $expirationText = ezpI18n::tr( 'sensor/expiring', 'Scaduto da' );
            $expirationLabel = 'danger';
        }
        else
        {
            $expirationText = ezpI18n::tr( 'sensor/expiring', 'Scade fra' );
            $expirationLabel = 'default';
        }
        $expirationText = $expirationText . ' ' . $diffResult->getText();

        $expirationInfo = new Post\ExpirationInfo();
        $expirationInfo->creationDateTime = $publishedDateTime;
        $expirationInfo->expirationDateTime = $expirationDateTime;
        $expirationInfo->label = $expirationLabel;
        $expirationInfo->text = $expirationText;
        $diff = $expirationDateTime->diff( $publishedDateTime );
        if ( $diff instanceof DateInterval )
        {
            $expirationInfo->days = $diff->days;
        }

        return $expirationInfo;
    }

    protected function getPostResolutionInfo( Post $post )
    {
        $resolutionInfo = null;
        if ( $this->getPostWorkflowStatus()->code == Post\WorkflowStatus::CLOSED )
        {
            $lastTimelineItem = $this->repository->getMessageService(
            )->loadTimelineItemCollectionByPost( $post )->lastMessage;
            $diffResult = Utils::getDateDiff( $post->published, $lastTimelineItem->published );
            $resolutionInfo = new Post\ResolutionInfo();
            $resolutionInfo->resolutionDateTime = $lastTimelineItem->published;
            $resolutionInfo->creationDateTime = $post->published;
            $resolutionInfo->text = $diffResult->getText();
        }

        return $resolutionInfo;
    }

    protected function getPostType()
    {
        $type = null;
        if ( isset( $this->contentObjectDataMap['type'] ) )
        {
            $typeIdentifier = $this->contentObjectDataMap['type']->toString();
            $type = new Post\Type();
            $type->identifier = $typeIdentifier;
            switch ( $typeIdentifier )
            {
                case 'suggerimento':
                    $type->name = ezpI18n::tr( 'openpa_sensor/type', 'Suggerimento' );
                    $type->label = 'warning';
                    break;

                case 'reclamo':
                    $type->name = ezpI18n::tr( 'openpa_sensor/type', 'Reclamo' );
                    $type->label = 'danger';
                    break;

                case 'segnalazione':
                    $type->name = ezpI18n::tr( 'openpa_sensor/type', 'Segnalazione' );
                    $type->label = 'info';
                    break;

                default:
                    $type->name = ucfirst( $typeIdentifier );
                    $type->label = 'info';
            }
        }

        return $type;
    }

    protected function getPostCurrentStatusByGroupIdentifier( $identifier )
    {
        foreach ( $this->repository->getSensorPostStates( $identifier ) as $state )
        {
            if ( in_array(
                $state->attribute( 'id' ),
                $this->contentObject->attribute( 'state_id_array' )
            ) )
            {
                return $state;
            }
        }

        return null;
    }

    protected function getPostCurrentStatus()
    {
        $status = new Post\Status();
        $state = $this->getPostCurrentStatusByGroupIdentifier( 'sensor' );
        if ( $state instanceof eZContentObjectState )
        {
            $status->identifier = $state->attribute( 'identifier' );
            $status->name = $state->currentTranslation()->attribute( 'name' );
            $status->label = 'info';
            if ( $state->attribute( 'identifier' ) == 'pending' )
            {
                $status->label = 'danger';
            }
            elseif ( $state->attribute( 'identifier' ) == 'open' )
            {
                $status->label = 'warning';
            }
            elseif ( $state->attribute( 'identifier' ) == 'close' )
            {
                $status->label = 'success';
            }

        }

        return $status;
    }

    protected function getPostPrivacyCurrentStatus()
    {
        $status = new Post\Status\Privacy();
        $state = $this->getPostCurrentStatusByGroupIdentifier( 'privacy' );
        if ( $state instanceof eZContentObjectState )
        {
            $state->setCurrentLanguage( $this->repository->getCurrentLanguage() );
            $status->identifier = $state->attribute( 'identifier' );
            $status->name = $state->currentTranslation()->attribute( 'name' );
            $status->label = 'info';
            if ( $state->attribute( 'identifier' ) == 'private' )
            {
                $status->label = 'default';
            }
        }

        return $status;
    }

    protected function getPostModerationCurrentStatus()
    {
        $status = new Post\Status\Moderation();
        $state = $this->getPostCurrentStatusByGroupIdentifier( 'moderation' );
        if ( $state instanceof eZContentObjectState )
        {
            $status->identifier = $state->attribute( 'identifier' );
            $status->name = $state->currentTranslation()->attribute( 'name' );
            $status->label = 'danger';
        }

        return $status;
    }

    protected function getPostImages()
    {
        $data = array();
        if ( isset( $this->contentObjectDataMap['image'] )
             && $this->contentObjectDataMap['image']->hasContent()
        )
        {
            /** @var eZImageAliasHandler $content */
            $content = $this->contentObjectDataMap['image']->content();
            $image = new Post\Field\Image();
            $image->fileName = $content->attribute( 'original_filename' );
            $structure = array(
                'width' => null,
                'height' => null,
                'mime_typ' => null,
                'filename' => null,
                'suffix' => null,
                'url' => null,
                'filesize' => null
            );
            $original = array_intersect_key( $content->attribute( 'original' ), $structure );
            $small = array_intersect_key( $content->attribute( 'small' ), $structure );
            $image->original = $original;
            $image->thumbnail = $small;
            $data[] = $image;
        }

        return $data;
    }

    protected function getPostAttachments()
    {
        $data = array();
        if ( isset( $this->contentObjectDataMap['attachment'] )
             && $this->contentObjectDataMap['attachment']->hasContent()
        )
        {
            $attachment = new Post\Field\Attachment();
            $data[] = $attachment;
        }

        return $data;
    }

    protected function getPostCategories()
    {
        $data = array();
        if ( isset( $this->contentObjectDataMap['category'] )
             && $this->contentObjectDataMap['category']->hasContent()
        )
        {
            $relationIds = explode( '-', $this->contentObjectDataMap['category']->toString() );
            /** @var eZContentObject[] $objects */
            $objects = eZContentObject::fetchIDArray( $relationIds );
            foreach ( $objects as $object )
            {
                $category = new Post\Field\Category();
                $category->id = $object->attribute( 'id' );
                $category->name = $object->name( false, $this->repository->getCurrentLanguage() );
                /** @var eZContentObjectAttribute[] $categoryDataMap */
                $categoryDataMap = $object->fetchDataMap( false, $this->repository->getCurrentLanguage() );
                if ( isset( $categoryDataMap['approver'] ) )
                {
                    $category->userIdList =  explode( '-', $categoryDataMap['approver']->toString() );
                }
                $data[] = $category;
            }
        }

        return $data;
    }

    protected function getPostAreas()
    {
        $data = array();
        if ( isset( $this->contentObjectDataMap['area'] )
             && $this->contentObjectDataMap['area']->hasContent()
        )
        {
            $relationIds = explode( '-', $this->contentObjectDataMap['area']->toString() );
            /** @var eZContentObject[] $objects */
            $objects = eZContentObject::fetchIDArray( $relationIds );
            foreach ( $objects as $object )
            {
                $area = new Post\Field\Area();
                $area->id = $object->attribute( 'id' );
                $area->name = $object->name( false, $this->repository->getCurrentLanguage() );
                $data[] = $area;
            }
        }

        return $data;
    }

    protected function getPostGeoLocation()
    {
        $geo = new Post\Field\GeoLocation();
        if ( isset( $this->contentObjectDataMap['geo'] )
             && $this->contentObjectDataMap['geo']->hasContent()
        )
        {
            /** @var \eZGmapLocation $content */
            $content = $this->contentObjectDataMap['geo']->content();
            $geo->latitude = $content->attribute( 'latitude' );
            $geo->longitude = $content->attribute( 'longitude' );
            $geo->address = $content->attribute( 'address' );
        }

        return $geo;
    }

    protected function getPostDescription()
    {
        if ( isset( $this->contentObjectDataMap['description'] ) )
        {
            return $this->contentObjectDataMap['description']->toString();
        }

        return false;
    }

    public function createPost( PostCreateStruct $post )
    {
        // TODO: Implement createPost() method.
    }

    public function updatePost( PostUpdateStruct $post )
    {
        // TODO: Implement updatePost() method.
    }

    public function deletePost( Post $post )
    {
        // TODO: Implement deletePost() method.
    }

    public function trashPost( Post $post )
    {
        // TODO: Implement trashPost() method.
    }

    public function restorePost( Post $post )
    {
        // TODO: Implement restorePost() method.
    }

    public function refreshPost( Post $post )
    {
        $timestamp = time();
        $this->getContentObject( $post->id );
        if ( !$this->contentObject instanceof eZContentObject )
        {
            throw new BaseException( "eZContentObject not found for id {$post->id}" );
        }

        $this->contentObject->setAttribute( 'modified', $timestamp );
        $this->contentObject->store();
        eZContentCacheManager::clearContentCacheIfNeeded( $this->contentObject->attribute( 'id' ) );
        eZSearch::addObject( $this->contentObject, true );
    }

    public function setPostStatus( Post $post, $status )
    {
        $this->getContentObject( $post->id );
        if ( !$this->contentObject instanceof eZContentObject )
        {
            throw new BaseException( "eZContentObject not found for id {$post->id}" );
        }

        if ( !$status instanceof eZContentObjectState )
        {
            list( $group, $identifier ) = explode( '.', $status );
            $states = $this->repository->getSensorPostStates( $group );
            $status = $states["{$group}.{$identifier}"];
        }

        if ( $status instanceof eZContentObjectState )
            $this->contentObject->assignState( $status );
    }

    public function setPostWorkflowStatus( Post $post, $status )
    {
        $states = $this->repository->getSensorPostStates( 'sensor' );
        $this->getCollaborationItem( $post->internalId );
        if ( !$this->collaborationItem instanceof eZCollaborationItem )
        {
            throw new BaseException( "eZCollaborationItem not found for id {$post->internalId}" );
        }

        $timestamp = time();

        $this->collaborationItem->setAttribute( self::COLLABORATION_FIELD_STATUS, $status );
        $this->collaborationItem->setAttribute( 'modified', $timestamp );
        $this->collaborationItem->setAttribute( self::COLLABORATION_FIELD_LAST_CHANGE, $timestamp );

        if ( $status == Post\WorkflowStatus::READ )
        {
            $this->setPostStatus( $post, $states['sensor.open'] );
        }
        elseif ( $status == Post\WorkflowStatus::CLOSED )
        {
            $this->collaborationItem->setAttribute( 'status', eZCollaborationItem::STATUS_INACTIVE );
            $this->repository->getParticipantService()->deactivatePostParticipants( $post );
            $this->setPostStatus( $post, $states['sensor.close'] );
        }
        elseif ( $status == Post\WorkflowStatus::WAITING )
        {
            $this->collaborationItem->setAttribute( 'status', eZCollaborationItem::STATUS_ACTIVE );
            $this->repository->getParticipantService()->activatePostParticipants( $post );
        }
        elseif ( $status == Post\WorkflowStatus::REOPENED )
        {
            $this->collaborationItem->setAttribute( 'status', eZCollaborationItem::STATUS_ACTIVE );
            $this->repository->getParticipantService()->activatePostParticipants( $post );
            $this->setPostStatus( $post, $states['sensor.pending'] );
        }
        $this->collaborationItem->sync();

        $this->refreshPost( $post );
    }

    public function setPostExpirationInfo( Post $post, $expiryDays )
    {
        $this->getCollaborationItem( $post->internalId );
        $this->collaborationItem->setAttribute(
            self::COLLABORATION_FIELD_EXPIRY,
            ExpiryTools::addDaysToTimestamp( $this->collaborationItem->attribute( 'created' ), $expiryDays )
        );
        $this->collaborationItem->store();
        $this->refreshPost( $post );
    }

    public function setPostCategory( Post $post, $category )
    {
        $this->getContentObjectDataMap( $post->id );
        if ( isset( $this->contentObjectDataMap['category'] ) )
        {
            $this->contentObjectDataMap['category']->fromString( $category );
            $this->contentObjectDataMap['category']->store();
            $this->refreshPost( $post );
        }
    }

    public function setPostArea( Post $post, $area )
    {
        $this->getContentObjectDataMap( $post->id );
        if ( isset( $this->contentObjectDataMap['area'] ) )
        {
            $this->contentObjectDataMap['area']->fromString( $area );
            $this->contentObjectDataMap['area']->store();
            $this->refreshPost( $post );
        }
    }

}
