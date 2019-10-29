<?php

namespace Opencontent\Sensor\Legacy\PostService;

use Opencontent\Sensor\Api\Values\Event;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post\WorkflowStatus;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Repository;
use eZContentObject;
use eZContentObjectVersion;
use eZCollaborationItem;
use Opencontent\Sensor\Legacy\PostService;
use Opencontent\Sensor\Legacy\Utils\ExpiryTools;
use Opencontent\Sensor\Api\Values\Post;
use SensorUserInfo;
use eZUser;

class PostInitializer
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
     * @var eZContentObjectVersion
     */
    private $contentObjectVersion;

    public function __construct($repository, $object, $version)
    {
        $this->repository = $repository;
        $this->contentObject = $object;
        $this->contentObjectVersion = $version;
    }

    public function init()
    {
        $user = $this->repository->getCurrentUser();

        $db = \eZDB::instance();
        $res = (array)$db->arrayQuery("SELECT id FROM ezcollab_item WHERE data_int1 = " . $this->contentObject->attribute('id') . " ORDER BY id desc");
        if (count($res) > 0) {
            $collaborationItem = eZCollaborationItem::fetch((int)$res[0]['id']);
        } else {
            $collaborationItem = eZCollaborationItem::create(
                $this->repository->getSensorCollaborationHandlerTypeString(),
                $user->id
            );
        }

        $collaborationItem->setAttribute(PostService::COLLABORATION_FIELD_OBJECT_ID, $this->contentObject->attribute('id'));
        $collaborationItem->setAttribute(PostService::COLLABORATION_FIELD_HANDLER, 'SensorHelper'); //@todo backward compatibility
        $collaborationItem->setAttribute(PostService::COLLABORATION_FIELD_STATUS, false);
        $collaborationItem->setAttribute(PostService::COLLABORATION_FIELD_LAST_CHANGE, 0);

        $collaborationItem->setAttribute(
            PostService::COLLABORATION_FIELD_EXPIRY,
            ExpiryTools::addDaysToTimestamp(
                time(),
                $this->repository->getSensorSettings()->get('DefaultPostExpirationDaysInterval')
            )
        );
        $collaborationItem->store();

        $post = $this->repository->getPostService()->loadPost($this->contentObject->attribute('id'));

        $this->setModeration($post, $user);
        $this->setPrivacy($post);

        $this->repository->getPostService()->setPostWorkflowStatus($post, WorkflowStatus::WAITING);

        $roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();

        $this->repository->getParticipantService()->addPostParticipant(
            $post,
            $user->id,
            $roles->getParticipantRoleById(ParticipantRole::ROLE_AUTHOR)
        );

        $scenarioLoader = new ScenarioLoader($this->repository, $post, $user);
        $scenario = $scenarioLoader->getScenario();

        foreach ($scenario->getApprovers() as $participantId) {
            $this->repository->getParticipantService()->addPostParticipant(
                $post,
                $participantId,
                $roles->getParticipantRoleById(ParticipantRole::ROLE_APPROVER)
            );
        }
        foreach ($scenario->getOwners() as $participantId) {
            $this->repository->getParticipantService()->addPostParticipant(
                $post,
                $participantId,
                $roles->getParticipantRoleById(ParticipantRole::ROLE_OWNER)
            );
        }
        foreach ($scenario->getObservers() as $participantId) {
            $this->repository->getParticipantService()->addPostParticipant(
                $post,
                $participantId,
                $roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER)
            );
        }

        $event = new Event();
        $event->identifier = 'on_create';
        $event->post = $post;
        $event->user = $user;
        $this->repository->getEventService()->fire($event);
    }

    public function refresh()
    {
        $user = $this->repository->getCurrentUser();
        $post = $this->repository->getPostService()->loadPost($this->contentObject->attribute('id'));
        $this->setModeration($post, $user);
        $this->setPrivacy($post);
        $this->repository->getPostService()->setPostWorkflowStatus($post, $post->workflowStatus->code);

        $event = new Event();
        $event->identifier = 'on_update';
        $event->post = $post;
        $event->user = $user;
        $this->repository->getEventService()->fire($event);
    }

    public function trash()
    {
        $user = $this->repository->getCurrentUser();
        $post = $this->repository->getPostService()->loadPost($this->contentObject->attribute('id'));

        foreach ($post->participants as $participant){
            $this->repository->getParticipantService()->trashPostParticipant($post, $participant->id);
        }

        $event = new Event();
        $event->identifier = 'on_trash';
        $event->post = $post;
        $event->user = $user;
        $this->repository->getEventService()->fire($event);
    }

    public function delete()
    {
        $user = $this->repository->getCurrentUser();
        $post = $this->repository->getPostService()->loadPost($this->contentObject->attribute('id'));
        self::deleteCollaborationStuff($post->internalId);

        $event = new Event();
        $event->identifier = 'on_remove';
        $event->post = $post;
        $event->user = $user;
        $this->repository->getEventService()->fire($event);
    }

    private function setPrivacy(Post $post)
    {
        /** @var \eZContentObjectAttribute[] $dataMap */
        $dataMap = $this->contentObjectVersion->attribute('data_map');
        if (isset($dataMap['privacy'])) {
            if (($dataMap['privacy']->attribute('data_type_string') == 'ezboolean' && $dataMap['privacy']->attribute('data_int') == 0)
                || ($dataMap['privacy']->attribute('data_type_string') == 'ezselection' && strtolower($dataMap['privacy']->attribute('data_text')) == 'no')) {
                $this->repository->getPostService()->setPostStatus($post, 'privacy.private');

                return false;
            }
        }

        $this->repository->getPostService()->setPostStatus($post, 'privacy.public');

        return true;
    }

    private function setModeration(Post $post, User $user)
    {
        if ($this->repository->isModerationEnabled()){
            $moderation = 'waiting';
        }else {
            $moderation = ($user->moderationMode) ? 'waiting' : 'skipped';
        }
        $this->repository->getPostService()->setPostStatus($post, "moderation.{$moderation}"); //@todo
    }

    public static function deleteCollaborationStuff($itemId)
    {
        $db = \eZDB::instance();
        $db->begin();
        $db->query("DELETE FROM ezcollab_item WHERE id = $itemId");
        $db->query("DELETE FROM ezcollab_item_group_link WHERE collaboration_id = $itemId");
        $res = $db->arrayQuery("SELECT message_id FROM ezcollab_item_message_link WHERE collaboration_id = $itemId");
        foreach ($res as $r) {
            $db->query("DELETE FROM ezcollab_simple_message WHERE id = {$r['message_id']}");
        }
        $db->query("DELETE FROM ezcollab_item_message_link WHERE collaboration_id = $itemId");
        $db->query("DELETE FROM ezcollab_item_participant_link WHERE collaboration_id = $itemId");
        $db->query("DELETE FROM ezcollab_item_status WHERE collaboration_id = $itemId");
        $db->commit();
    }
}