<?php

namespace Opencontent\Sensor\Legacy\PostService;

use eZImageAliasHandler;
use DateInterval;
use ezpI18n;
use Opencontent\Sensor\Api\ScenarioService;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use eZContentObject;
use eZContentObjectAttribute;
use eZCollaborationItem;
use Opencontent\Sensor\Api\Values\Scenario\SearchScenarioParameters;
use Opencontent\Sensor\Legacy\PostService;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils;
use eZContentObjectState;

class PostBuilder
{
    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var eZContentObject
     */
    private $contentObject;

    /**
     * @var eZContentObjectAttribute[]
     */
    private $contentObjectDataMap;

    /**
     * @var eZCollaborationItem
     */
    private $collaborationItem;

    public function __construct($repository, eZContentObject $object, eZCollaborationItem $collaborationItem)
    {
        $this->repository = $repository;
        $this->contentObject = $object;
        $this->contentObjectDataMap = $this->contentObject->dataMap();
        $this->collaborationItem = $collaborationItem;
    }

    public function build()
    {
        $post = new Post();
        $post->id = (int)$this->contentObject->attribute('id');
        $post->internalId = (int)$this->collaborationItem->attribute('id');

        $post->published = Utils::getDateTimeFromTimestamp(
            $this->contentObject->attribute('published')
        );
        $post->modified = Utils::getDateTimeFromTimestamp(
            $this->contentObject->attribute('modified')
        );;

        $post->expirationInfo = $this->loadPostExpirationInfo();
        $post->resolutionInfo = $this->loadPostResolutionInfo($post);

        $post->privacy = $this->loadPostPrivacyCurrentStatus();
        $post->status = $this->loadPostCurrentStatus();
        $post->moderation = $this->loadPostModerationCurrentStatus();
        $post->workflowStatus = $this->loadPostWorkflowStatus();

        $post->subject = htmlspecialchars($this->contentObject->name(
            false,
            $this->repository->getCurrentLanguage()
        ));
        $post->description = $this->loadPostDescription();
        $post->type = $this->loadPostType();
        $post->images = $this->loadPostImages();
        $post->attachments = $this->loadPostAttachments();
        $post->categories = $this->loadPostCategories();
        $post->areas = $this->loadPostAreas();
        $post->geoLocation = $this->loadPostGeoLocation();

        $post->participants = $this->repository->getParticipantService()->loadPostParticipants(
            $post
        );
        $post->reporter = $this->loadPostReporter();
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

        $post->author = $this->repository->getUserService()->loadUser($this->contentObject->attribute('owner_id'));
        $authorName = $this->loadPostAuthorName();
        if ($authorName) {
            $post->author->name = $authorName;
        }

        $post->comments = $this->repository->getMessageService()->loadCommentCollectionByPost($post);
        $post->privateMessages = $this->repository->getMessageService()->loadPrivateMessageCollectionByPost($post);
        $post->timelineItems = $this->repository->getMessageService()->loadTimelineItemCollectionByPost($post);
        $post->responses = $this->repository->getMessageService()->loadResponseCollectionByPost($post);
        $post->audits = $this->repository->getMessageService()->loadAuditCollectionByPost($post);

        $post->meta = $this->loadPostMeta();

        $post->relatedItems = $this->loadPostRelatedItems();

        $post->channel = $this->loadPostChannel();

        $this->checkApproversConsistency($post);

        return $post;
    }

    private function checkApproversConsistency(Post $post)
    {
        if ($this->collaborationItem->attribute(PostService::COLLABORATION_FIELD_STATUS) !== false) {

            $approversCount = 0;
            foreach ($post->approvers as $approver){
                if ($approver->type !== 'removed'){
                    $approversCount++;
                }
            }

            if ($approversCount === 0) {
                $this->repository->getLogger()->warning("Reset post approvers in post $post->id");

                $scenario = $this->repository
                    ->getScenarioService()
                    ->getFirstScenariosByTrigger($post, ScenarioService::INIT_POST, new SearchScenarioParameters(true));
                $roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();
                foreach ($scenario->getApprovers() as $participantId) {
                    $this->repository->getParticipantService()->addPostParticipant(
                        $post,
                        $participantId,
                        $roles->getParticipantRoleById(ParticipantRole::ROLE_APPROVER)
                    );
                }
                $post->approvers = Participant\ApproverCollection::fromCollection(
                    $this->repository->getParticipantService()->loadPostParticipantsByRole(
                        $post,
                        ParticipantRole::ROLE_APPROVER
                    )
                );
            }
        }
    }

    protected function loadPostMeta()
    {
        if (isset($this->contentObjectDataMap['meta'])
            && $this->contentObjectDataMap['meta']->hasContent()
        ) {
            return (array)json_decode($this->contentObjectDataMap['meta']->toString(), true);
        }

        return null;
    }

    protected function loadPostReporter()
    {
        $reporter = clone $this->repository->getUserService()->loadUser($this->contentObject->attribute('owner_id'));
        if (isset($this->contentObjectDataMap['reporter']) && $this->contentObjectDataMap['reporter']->hasContent()) {
            $reporter = $this->repository->getUserService()->loadUser((int)$this->contentObjectDataMap['reporter']->toString());
        }

        return $reporter;
    }

    protected function loadPostAuthorName()
    {
        $authorName = false;
        if (isset($this->contentObjectDataMap['on_behalf_of'])
            && $this->contentObjectDataMap['on_behalf_of']->hasContent()
            && !is_numeric($this->contentObjectDataMap['on_behalf_of']->toString())
        ) {
            $authorName = $this->contentObjectDataMap['on_behalf_of']->toString();
            if (isset($this->contentObjectDataMap['on_behalf_of_detail'])
                && $this->contentObjectDataMap['on_behalf_of_detail']->hasContent()
            ) {
                $authorName .= ', ' . $this->contentObjectDataMap['on_behalf_of_detail']->toString();
            }
        }

        return $authorName;
    }

    protected function loadPostWorkflowStatus()
    {
        return Post\WorkflowStatus::instanceByCode(
            $this->collaborationItem->attribute(PostService::COLLABORATION_FIELD_STATUS)
        );
    }

    protected function loadPostExpirationInfo()
    {
        $publishedDateTime = Utils::getDateTimeFromTimestamp(
            $this->contentObject->attribute('published')
        );
        $expirationDateTime = Utils::getDateTimeFromTimestamp(
            intval($this->collaborationItem->attribute(PostService::COLLABORATION_FIELD_EXPIRY))
        );

        $expirationInfo = new Post\ExpirationInfo();
        $expirationInfo->creationDateTime = clone $publishedDateTime;
        $expirationInfo->expirationDateTime = clone $expirationDateTime;
        $diff = $expirationDateTime->setTime(0, 0)->diff($publishedDateTime->setTime(0, 0));
        if ($diff instanceof DateInterval) {
            $expirationInfo->days = $diff->days;
        }

        return $expirationInfo;
    }

    protected function loadPostResolutionInfo(Post $post)
    {
        $resolutionInfo = null;
        if ($this->loadPostWorkflowStatus()->is(Post\WorkflowStatus::CLOSED)) {
            $closedItem = $this->repository->getMessageService()->loadTimelineItemCollectionByPost($post)->getByType('closed')->last();
            if ($closedItem) {
                $diffResult = Utils::getDateDiff($post->published, $closedItem->published);
                $resolutionInfo = new Post\ResolutionInfo();
                $resolutionInfo->resolutionDateTime = $closedItem->published;
                $resolutionInfo->creationDateTime = $post->published;
                $resolutionInfo->text = $diffResult->getText();
            }
        }

        return $resolutionInfo;
    }

    protected function loadPostType()
    {
        $type = null;
        if (isset($this->contentObjectDataMap['type'])) {
            $typeIdentifier = $this->contentObjectDataMap['type']->toString();
            $type = $this->repository->getPostTypeService()->loadPostType($typeIdentifier);
        }

        return $type;
    }

    protected function loadPostCurrentStatusByGroupIdentifier($identifier)
    {
        foreach ($this->repository->getSensorPostStates($identifier) as $state) {
            if (in_array(
                $state->attribute('id'),
                $this->contentObject->attribute('state_id_array')
            )) {
                return $state;
            }
        }

        return null;
    }

    protected function loadPostCurrentStatus()
    {
        $status = new Post\Status();
        $state = $this->loadPostCurrentStatusByGroupIdentifier('sensor');
        if ($state instanceof eZContentObjectState) {
            $status->identifier = $state->attribute('identifier');
            $status->name = $state->translationByLocale($this->repository->getCurrentLanguage())->attribute('name');
            $status->label = 'info';
            if ($state->attribute('identifier') == 'pending') {
                $status->label = 'danger';
            } elseif ($state->attribute('identifier') == 'open') {
                $status->label = 'warning';
            } elseif ($state->attribute('identifier') == 'close') {
                $status->label = 'success';
            }

        }

        return $status;
    }

    protected function loadPostPrivacyCurrentStatus()
    {
        $status = new Post\Status\Privacy();
        $state = $this->loadPostCurrentStatusByGroupIdentifier('privacy');
        if ($state instanceof eZContentObjectState) {
            $status->identifier = $state->attribute('identifier');
            $status->name = $state->translationByLocale($this->repository->getCurrentLanguage())->attribute('name');
            $status->label = 'info';
            if ($state->attribute('identifier') == 'private') {
                $status->label = 'default';
            }
        }

        return $status;
    }

    protected function loadPostModerationCurrentStatus()
    {
        $status = new Post\Status\Moderation();
        $state = $this->loadPostCurrentStatusByGroupIdentifier('moderation');
        if ($state instanceof eZContentObjectState) {
            $status->identifier = $state->attribute('identifier');
            $status->name = $state->translationByLocale($this->repository->getCurrentLanguage())->attribute('name');
            $status->label = 'danger';
        }

        return $status;
    }

    protected function loadPostImages()
    {
        $data = array();
        if (isset($this->contentObjectDataMap['image'])
            && $this->contentObjectDataMap['image']->hasContent()
        ) {
            $attribute = $this->contentObjectDataMap['image'];
            /** @var eZImageAliasHandler $content */
            $content = $this->contentObjectDataMap['image']->content();
            $image = new Post\Field\Image();
            $image->fileName = $content->attribute('original_filename');
            $structure = array(
                'width' => null,
                'height' => null,
                'mime_type' => null,
                'filename' => null,
                'suffix' => null,
                'url' => null,
                'filesize' => null
            );

            $original = array_intersect_key($content->attribute('original'), $structure);
            $thumb = array_intersect_key($content->attribute('large'), $structure);
            $image->original = $original;
            $image->thumbnail = $thumb;
            $image->apiUrl = 'api/sensor/file/'
                . $attribute->attribute('id') . '-' . $attribute->attribute('version') . '-' . $attribute->attribute('language_code')
                . '/' . base64_encode($image->fileName)
                . '/' . $image->fileName;

            $data[] = $image;
        }

        if (isset($this->contentObjectDataMap['images'])
            && $this->contentObjectDataMap['images']->hasContent()
        ) {
            $attribute = $this->contentObjectDataMap['images'];
            /** @var \eZMultiBinaryFile[] $files */
            $files = $this->contentObjectDataMap['images']->content();
            foreach ($files as $file) {
                $image = new Post\Field\Image();
                $image->fileName = $file->attribute('original_filename');

                $fileHandler = \eZClusterFileHandler::instance($file->attribute('filepath'));
                $fileHandler->fetch();

                $width = null;
                $height = null;
                $info = getimagesize($file->attribute('filepath'));
                if ($info) {
                    $width = $info[0];
                    $height = $info[1];
                }

                $image->apiUrl = 'api/sensor/file/'
                    . $attribute->attribute('id') . '-' . $attribute->attribute('version') . '-' . $attribute->attribute('language_code')
                    . '/' . base64_encode($file->attribute('filename'))
                    . '/' . $file->attribute('original_filename');

                $url = 'ocmultibinary/download/' . $attribute->attribute('contentobject_id')
                    . '/' . $attribute->attribute('id')
                    . '/' . $attribute->attribute('version')
                    . '/' . $file->attribute('filename')
                    . '/file/' . $file->attribute('original_filename');


                $image->original = $image->thumbnail = array(
                    'width' => $width,
                    'height' => $height,
                    'mime_type' => $fileHandler->dataType(),
                    'filename' => $file->attribute('original_filename'),
                    'suffix' => \eZFile::suffix($file->attribute('original_filename')),
                    'url' => $url,
                    'filesize' => $fileHandler->size()
                );
                $data[] = $image;

                $fileHandler->deleteLocal();
            }
        }

        return $data;
    }

    protected function loadPostAttachments()
    {
        $data = array();
        if (isset($this->contentObjectDataMap['attachment'])
            && $this->contentObjectDataMap['attachment']->hasContent()
        ) {
            $attribute = $this->contentObjectDataMap['attachment'];
            $files = array();
            $prefix = 'content';
            if ($attribute->attribute('data_type_string') == \eZBinaryFileType::DATA_TYPE_STRING) {
                $files = [$attribute->content()];
            } elseif (class_exists('\OCMultiBinaryType') && $attribute->attribute('data_type_string') == \OCMultiBinaryType::DATA_TYPE_STRING) {
                $files = $attribute->content();
                $prefix = 'ocmultibinary';
            }

            foreach ($files as $file) {
                if ($file instanceof \eZBinaryFile) {
                    $attachment = new Post\Field\Attachment();
                    $attachment->filename = $file->attribute('original_filename');

                    $attachment->downloadUrl = $prefix . '/download/'
                        . $this->contentObjectDataMap['attachment']->attribute('contentobject_id')
                        . '/' . $this->contentObjectDataMap['attachment']->attribute('id')
                        . '/' . $this->contentObjectDataMap['attachment']->attribute('version');
                    if ($prefix == 'ocmultibinary'){
                        $attachment->downloadUrl .= '/' . $file->attribute('filename') . '/file';
                    }
                    $attachment->downloadUrl .= '/' . $file->attribute('original_filename');

                    $attachment->apiUrl = 'api/sensor/file/'
                        . $attribute->attribute('id') . '-' . $attribute->attribute('version') . '-' . $attribute->attribute('language_code')
                        . '/' . base64_encode($file->attribute('filename'))
                        . '/' . $file->attribute('original_filename');

                    $data[] = $attachment;
                }
            }
        }

        return $data;
    }

    protected function loadPostCategories()
    {
        $data = array();
        if (isset($this->contentObjectDataMap['category'])
            && $this->contentObjectDataMap['category']->hasContent()
        ) {
            $relationIds = explode('-', $this->contentObjectDataMap['category']->toString());

            foreach ($relationIds as $id) {
                try {
                    $data[] = $this->repository->getCategoryService()->loadCategory($id);
                } catch (\Exception $e) {

                }
            }
        }

        return $data;
    }

    protected function loadPostAreas()
    {
        $data = array();
        if (isset($this->contentObjectDataMap['area'])
            && $this->contentObjectDataMap['area']->hasContent()
        ) {
            $relationIds = explode('-', $this->contentObjectDataMap['area']->toString());

            foreach ($relationIds as $id) {
                try {
                    $data[] = $this->repository->getAreaService()->loadArea($id);
                } catch (\Exception $e) {

                }
            }
        }

        return $data;
    }

    protected function loadPostGeoLocation()
    {
        $geo = new Post\Field\GeoLocation();
        if (isset($this->contentObjectDataMap['geo'])
            && $this->contentObjectDataMap['geo']->hasContent()
        ) {
            /** @var \eZGmapLocation $content */
            $content = $this->contentObjectDataMap['geo']->content();
            $geo->latitude = $content->attribute('latitude');
            $geo->longitude = $content->attribute('longitude');
            $geo->address = htmlspecialchars(html_entity_decode($content->attribute('address')));
        }

        return $geo;
    }

    protected function loadPostDescription()
    {
        if (isset($this->contentObjectDataMap['description'])) {
            return htmlspecialchars($this->contentObjectDataMap['description']->toString());
        }

        return false;
    }

    protected function loadPostRelatedItems()
    {
        $list = [];
        $relatedObjects = (array)$this->contentObject->relatedContentObjectList(false, false, 0, false,
            ['AllRelations' => \eZContentFunctionCollection::contentobjectRelationTypeMask(['common']), 'AsObject' => false]
        );
        $relatedObjects = array_merge($relatedObjects, (array)$this->contentObject->reverseRelatedObjectList(false, 0, false,
            ['AllRelations' => \eZContentFunctionCollection::contentobjectRelationTypeMask(['common']), 'AsObject' => false]
        ));
        foreach ($relatedObjects as $relatedObject){
            if ($relatedObject['contentclass_identifier'] == $this->repository->getPostContentClassIdentifier()){
                $list[] = (int) $relatedObject['id'];
            }
        }

        return array_unique($list);
    }

    protected function loadPostChannel()
    {
        if (isset($this->contentObjectDataMap['on_behalf_of_mode'])
            && $this->contentObjectDataMap['on_behalf_of_mode']->hasContent()
        ) {
            $channel = $this->contentObjectDataMap['on_behalf_of_mode']->toString();
            return $this->repository->getChannelService()->loadPostChannel($channel);
        }

        return $this->repository->getChannelService()->loadPostDefaultChannel();
    }
}