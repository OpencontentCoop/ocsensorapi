<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\BaseException;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantCollection;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Core\ParticipantService as ParticipantServiceBase;
use eZContentObject;
use eZCollaborationItemParticipantLink;
use ezpI18n;
use Opencontent\Sensor\Api\Values\ParticipantRoleCollection;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use eZCollaborationGroup;
use eZCollaborationItemGroupLink;
use eZPersistentObject;
use eZCollaborationItemStatus;
use Opencontent\Sensor\Legacy\Utils\Translator;


class ParticipantService extends ParticipantServiceBase
{
    const MAIN_COLLABORATION_GROUP_NAME = 'Sensor';

    const TRASH_COLLABORATION_GROUP_NAME = 'Trash';

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var ParticipantCollection[]
     */
    protected $participantsByPost = array();

    /**
     * @var ParticipantRoleCollection
     */
    protected $participantRoles;

    protected static $operatorsByGroup = array();

    /**
     * @return ParticipantRoleCollection
     */
    public function loadParticipantRoleCollection()
    {
        if ($this->participantRoles === null) {
            $this->participantRoles = new ParticipantRoleCollection();

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_STANDARD;
            $role->identifier = ParticipantRole::ROLE_STANDARD;
            $role->name = Translator::translate('Standard', 'role_name');
            $this->participantRoles->addParticipantRole($role);

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_OBSERVER;
            $role->identifier = ParticipantRole::ROLE_OBSERVER;
            $role->name = Translator::translate('Observer', 'role_name');
            $this->participantRoles->addParticipantRole($role);

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_OWNER;
            $role->identifier = ParticipantRole::ROLE_OWNER;
            $role->name = Translator::translate('In charge of', 'role_name');
            $this->participantRoles->addParticipantRole($role);

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_APPROVER;
            $role->identifier = ParticipantRole::ROLE_APPROVER;
            $role->name = Translator::translate('Reference for the citizen', 'role_name');
            $this->participantRoles->addParticipantRole($role);

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_AUTHOR;
            $role->identifier = ParticipantRole::ROLE_AUTHOR;
            $role->name = Translator::translate('Author', 'role_name');
            $this->participantRoles->addParticipantRole($role);
        }

        return $this->participantRoles;
    }

    public function loadPostParticipantById(Post $post, $id)
    {
        return $this->internalLoadPostParticipants($post)->getParticipantById($id);
    }

    /**
     * @param Post $post
     * @param $role
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipantsByRole(Post $post, $role)
    {
        return $this->internalLoadPostParticipants($post)->getParticipantsByRole($role);
    }

    /**
     * @param Post $post
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipants(Post $post)
    {
        return $this->internalLoadPostParticipants($post);
    }

    public function addPostParticipant(Post $post, $id, ParticipantRole $role)
    {
        $contentObject = eZContentObject::fetch($id);
        if (!$contentObject instanceof eZContentObject) {
            throw new BaseException("Participant $id not found");
        }

        $link = eZCollaborationItemParticipantLink::fetch(
            $post->internalId,
            $id
        );

        if (!$link instanceof eZCollaborationItemParticipantLink) {
            if (in_array($contentObject->attribute('contentclass_id'), \eZUser::contentClassIDs()))
                $type = eZCollaborationItemParticipantLink::TYPE_USER;
            else
                $type = eZCollaborationItemParticipantLink::TYPE_USERGROUP;

            $link = eZCollaborationItemParticipantLink::create(
                $post->internalId,
                $id,
                $role->id,
                $type
            );
            $link->store();
            eZCollaborationItemGroupLink::addItem(
                $this->getMainCollaborationGroup($id)->attribute('id'),
                $post->internalId,
                $id
            );
            $this->setPostParticipantActive($post, $id, true);
        } else {
            $link->setAttribute('participant_role', $role->id);
            $link->sync();
        }
        $GLOBALS['eZCollaborationItemParticipantLinkListCache'] = array();
        unset($this->participantsByPost[$post->internalId]);
        $this->internalLoadPostParticipants($post);
    }

    public function trashPostParticipant(Post $post, $id)
    {
        /** @var eZCollaborationItemGroupLink $group */
        $groupLink = eZPersistentObject::fetchObject(
            eZCollaborationItemGroupLink::definition(),
            null,
            ['collaboration_id' => $post->internalId, 'user_id' => $id]
        );
        if ($groupLink instanceof eZCollaborationItemGroupLink) {
            $db = \eZDB::instance();
            $db->begin();
            $groupLink->remove();
            $trashGroupLink = eZCollaborationItemGroupLink::create(
                $post->internalId,
                $this->getTrashCollaborationGroup($id)->attribute('id'),
                $id
            );
            $trashGroupLink->store();
            $db->commit();
        }
    }

    public function removePostParticipant(Post $post, $id)
    {
        $db = \eZDB::instance();
        $db->begin();
        $link = eZCollaborationItemParticipantLink::fetch(
            $post->internalId,
            $id
        );
        if ($link instanceof eZCollaborationItemParticipantLink) {
            $link->remove();
        }
        $groupLink = eZPersistentObject::fetchObject(
            eZCollaborationItemGroupLink::definition(),
            null,
            ['collaboration_id' => $post->internalId, 'user_id' => $id]
        );
        if ($groupLink instanceof eZCollaborationItemGroupLink) {
            $groupLink->remove();
        }
        $db->commit();
    }

    public function restorePostParticipant(Post $post, $id)
    {
        /** @var eZCollaborationItemGroupLink $group */
        $groupLink = eZPersistentObject::fetchObject(
            eZCollaborationItemGroupLink::definition(),
            null,
            ['collaboration_id' => $post->internalId, 'user_id' => $id]
        );
        if ($groupLink instanceof eZCollaborationItemGroupLink) {
            $db = \eZDB::instance();
            $db->begin();
            $groupLink->remove();
            $sensorGroupLink = eZCollaborationItemGroupLink::create(
                $post->internalId,
                $this->getMainCollaborationGroup($id)->attribute('id'),
                $id
            );
            $sensorGroupLink->store();
            $db->commit();
        }
    }

    public function activatePostParticipants(Post $post)
    {
        foreach ($this->loadPostParticipants($post) as $participant)
            $this->setPostParticipantActive($post, $participant->id, true);
    }

    public function deactivatePostParticipants(Post $post)
    {
        foreach ($this->loadPostParticipants($post) as $participant)
            $this->setPostParticipantActive($post, $participant->id, false);
    }

    protected function setPostParticipantActive(Post $post, $id, $active)
    {
        eZCollaborationItemStatus::updateFields($post->internalId, $id, array('is_active' => intval($active)));
    }

    protected function internalLoadPostParticipants(Post $post)
    {
        $postInternalId = $post->internalId;
        if ($postInternalId && !isset($this->participantsByPost[$postInternalId])) {
            $this->participantsByPost[$postInternalId] = new ParticipantCollection();

            /** @var eZCollaborationItemParticipantLink[] $participantLinks */
            $participantLinks = eZCollaborationItemParticipantLink::fetchParticipantList(
                array(
                    'item_id' => $postInternalId,
                    'limit' => 1000 // avoid ez cache
                )
            );
            $participantIdList = array();
            foreach ($participantLinks as $participantLink) {
                $participantIdList[] = $participantLink->attribute('participant_id');
            }
            if (count($participantIdList)) {
                /** @var eZContentObject[] $objects */
                $objects = eZContentObject::fetchIDArray($participantIdList);

                foreach ($participantLinks as $participantLink) {
                    $id = $participantLink->attribute('participant_id');
                    $object = isset($objects[$id]) ? $objects[$id] : null;
                    $participant = $this->internalLoadParticipant(
                        $participantLink,
                        $object
                    );
                    if ($participant->type != Participant::TYPE_REMOVED) {
                        $this->participantsByPost[$postInternalId]->addParticipant($participant);
                    }
                }
            }
        }

        return $this->participantsByPost[$postInternalId];
    }

    protected function internalLoadParticipant(
        eZCollaborationItemParticipantLink $participantLink,
        eZContentObject $contentObject = null
    )
    {
        $role = $this->loadParticipantRoleCollection()->getParticipantRoleById($participantLink->attribute('participant_role'));
        $participant = new Participant();
        $participant->id = $participantLink->attribute('participant_id');
        $participant->roleIdentifier = $role->identifier;
        $participant->roleName = $role->name;
        $participant->lastAccessDateTime = Utils::getDateTimeFromTimestamp(
            $participantLink->attribute('last_read')
        );
        $participant->type = Participant::TYPE_REMOVED;
        $participant->name = '?';

        if ($contentObject instanceof eZContentObject) {
            $participant->name = $contentObject->name(
                false,
                $this->repository->getCurrentLanguage()
            );
            if ($participantLink->attribute('participant_type') == eZCollaborationItemParticipantLink::TYPE_USER) {
                $user = $this->repository->getUserService()->loadUser($contentObject->attribute('id'));
                $participant->addUser($user);
                $participant->description = $user->description;
                $participant->type = Participant::TYPE_USER;

            } elseif ($participantLink->attribute('participant_type') == eZCollaborationItemParticipantLink::TYPE_USERGROUP) {

                try {
                    $operators = $this->loadOperatorsByGroup($contentObject->attribute('id'));
                    foreach ($operators as $operator) {
                        $participant->addUser($operator);
                    }

                } catch (\Exception $e) {
                    $contentNode = $contentObject->mainNode();
                    if ($contentNode instanceof \eZContentObjectTreeNode) {
                        /** @var \eZContentObjectTreeNode $child */
                        foreach ($contentNode->children() as $child) {
                            $participant->addUser(
                                $this->repository->getUserService()->loadUser(
                                    $child->attribute('contentobject_id')
                                )
                            );
                        }
                    }
                }

                $participant->type = Participant::TYPE_GROUP;
            }
        }

        return $participant;
    }

    private function loadOperatorsByGroup($groupId)
    {
        if (!isset(self::$operatorsByGroup[$groupId])) {
            $group = $this->repository->getGroupService()->loadGroup($groupId, []);
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*', []);
            $operators = $operatorResult['items'];
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
            self::$operatorsByGroup[$groupId] = $operators;
        }
        return self::$operatorsByGroup[$groupId];
    }

    private function recursiveLoadOperatorsByGroup(Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next'], []);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
        }

        return $operators;
    }

    /**
     * @param int $userId
     *
     * @return eZCollaborationGroup|null
     */
    protected function getMainCollaborationGroup($userId)
    {
        return $this->getCollaborationGroup(self::MAIN_COLLABORATION_GROUP_NAME, $userId);
    }

    /**
     * @param int $userId
     *
     * @return eZCollaborationGroup|null
     */
    protected function getTrashCollaborationGroup($userId)
    {
        return $this->getCollaborationGroup(self::TRASH_COLLABORATION_GROUP_NAME, $userId);
    }

    /**
     * @param string $groupName
     * @param int $userId
     *
     * @return eZCollaborationGroup|null
     */
    protected function getCollaborationGroup($groupName, $userId)
    {
        $group = eZPersistentObject::fetchObject(
            eZCollaborationGroup::definition(),
            null,
            array(
                'user_id' => $userId,
                'title' => $groupName
            )
        );
        if (!$group instanceof eZCollaborationGroup && $groupName != '') {
            $group = eZCollaborationGroup::instantiate(
                $userId,
                $groupName
            );
        }
        return $group;
    }

}
