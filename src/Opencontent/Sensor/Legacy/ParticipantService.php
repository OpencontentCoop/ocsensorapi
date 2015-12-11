<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Api\Exception\BaseException;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Participant\ApproverCollection;
use OpenContent\Sensor\Api\Values\Participant\ObserverCollection;
use OpenContent\Sensor\Api\Values\Participant\OwnerCollection;
use OpenContent\Sensor\Api\Values\ParticipantCollection;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Core\ParticipantService as ParticipantServiceBase;
use eZContentObject;
use eZCollaborationItemParticipantLink;
use ezpI18n;
use OpenContent\Sensor\Api\Values\ParticipantRoleCollection;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use eZCollaborationGroup;
use eZCollaborationItemGroupLink;
use eZPersistentObject;
use eZUser;
use eZCollaborationItemStatus;
use ezpEvent;


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

    public function loadParticipantRoleCollection()
    {
        if ( $this->participantRoles === null )
        {
            $this->participantRoles = new ParticipantRoleCollection();

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_STANDARD;
            $role->identifier = ParticipantRole::ROLE_STANDARD;
            $role->name = ezpI18n::tr( 'sensor/role_name', 'Standard' );
            $this->participantRoles->addParticipantRole( $role );

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_OBSERVER;
            $role->identifier = ParticipantRole::ROLE_OBSERVER;
            $role->name = ezpI18n::tr( 'sensor/role_name', 'Osservatore' );
            $this->participantRoles->addParticipantRole( $role );

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_OWNER;
            $role->identifier = ParticipantRole::ROLE_OWNER;
            $role->name = ezpI18n::tr( 'sensor/role_name', 'In carico a' );
            $this->participantRoles->addParticipantRole( $role );

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_APPROVER;
            $role->identifier = ParticipantRole::ROLE_APPROVER;
            $role->name = ezpI18n::tr( 'sensor/role_name', 'Riferimento per il cittadino' );
            $this->participantRoles->addParticipantRole( $role );

            $role = new ParticipantRole();
            $role->id = eZCollaborationItemParticipantLink::ROLE_AUTHOR;
            $role->identifier = ParticipantRole::ROLE_AUTHOR;
            $role->name = ezpI18n::tr( 'sensor/role_name', 'Autore' );
            $this->participantRoles->addParticipantRole( $role );
        }

        return $this->participantRoles;
    }

    public function loadPostParticipantById( Post $post, $id )
    {
        return $this->internalLoadPostParticipants( $post )->getParticipantById( $id );
    }

    /**
     * @param Post $post
     * @param $role
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipantsByRole( Post $post, $role )
    {
        return $this->internalLoadPostParticipants( $post )->getParticipantsByRole( $role );
    }

    /**
     * @param Post $post
     *
     * @return ParticipantCollection
     */
    public function loadPostParticipants( Post $post )
    {
        return $this->internalLoadPostParticipants( $post );
    }

    public function addPostParticipant( Post $post, $id, ParticipantRole $role )
    {
        $contentObject = eZContentObject::fetch( $id );
        if ( !$contentObject instanceof eZContentObject )
        {
            throw new BaseException( "User $id not found" );
        }

        $link = eZCollaborationItemParticipantLink::fetch(
            $post->internalId,
            $id
        );

        if ( !$link instanceof eZCollaborationItemParticipantLink )
        {
            if ( in_array( $contentObject->attribute( 'contentclass_id' ), \eZUser::contentClassIDs() ) )
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
                $this->getMainCollaborationGroup( $id )->attribute( 'id' ),
                $post->internalId,
                $id
            );
            $this->setPostParticipantActive( $post, $id, true );
        }
        else
        {
            $link->setAttribute( 'participant_role', $role->id );
            $link->sync();
        }
        $GLOBALS['eZCollaborationItemParticipantLinkListCache'] = array();
        ezpEvent::getInstance()->notify( 'sensor/add_participant', array( $id, $role->id, $post ) );
        unset( $this->participantsByPost[$post->internalId] );
    }

    public function trashPostParticipant( Post $post, $id )
    {
        // TODO: Implement trashPostParticipant() method.
    }

    public function restorePostParticipant( Post $post, $id )
    {
        // TODO: Implement restorePostParticipant() method.
    }

    public function activatePostParticipants( Post $post )
    {
        foreach( $this->loadPostParticipants( $post ) as $participant )
            $this->setPostParticipantActive( $post, $participant->id, true );
    }

    public function deactivatePostParticipants( Post $post )
    {
        foreach( $this->loadPostParticipants( $post ) as $participant )
            $this->setPostParticipantActive( $post, $participant->id, false );
    }

    protected function setPostParticipantActive( Post $post, $id, $active )
    {
        eZCollaborationItemStatus::updateFields( $post->internalId, $id, array( 'is_active' => intval($active) ) );
    }

    protected function internalLoadPostParticipants( Post $post )
    {
        $postInternalId = $post->internalId;
        if ( !isset( $this->participantsByPost[$postInternalId] ) )
        {
            $this->participantsByPost[$postInternalId] = new ParticipantCollection();

            /** @var eZCollaborationItemParticipantLink[] $participantLinks */
            $participantLinks = eZCollaborationItemParticipantLink::fetchParticipantList(
                array(
                    'item_id' => $postInternalId,
                    'limit' => 1000 // avoid ez cache
                )
            );
            $participantIdList = array();
            foreach ( $participantLinks as $participantLink )
            {
                $participantIdList[] = $participantLink->attribute( 'participant_id' );
            }
            /** @var eZContentObject[] $objects */
            $objects = eZContentObject::fetchIDArray( $participantIdList );

            foreach ( $participantLinks as $participantLink )
            {
                $id = $participantLink->attribute( 'participant_id' );
                $object = isset( $objects[$id] ) ? $objects[$id] : null;
                $participant = $this->internalLoadParticipant(
                    $participantLink,
                    $object
                );

                $this->participantsByPost[$postInternalId]->addParticipant( $participant );
            }
        }

        return $this->participantsByPost[$postInternalId];
    }

    protected function internalLoadParticipant(
        eZCollaborationItemParticipantLink $participantLink,
        eZContentObject $contentObject = null
    )
    {
        $role = $this->loadParticipantRoleCollection()->getParticipantRoleById( $participantLink->attribute( 'participant_role' ) );
        $participant = new Participant();
        $participant->id = $participantLink->attribute( 'participant_id' );
        $participant->roleIdentifier = $role->identifier;
        $participant->roleName = $role->name;
        $participant->lastAccessDateTime = Utils::getDateTimeFromTimestamp(
            $participantLink->attribute( 'last_read' )
        );

        if ( $contentObject instanceof eZContentObject )
        {
            $participant->name = $contentObject->name(
                false,
                $this->repository->getCurrentLanguage()
            );
            if ( $participantLink->attribute( 'participant_type' ) == eZCollaborationItemParticipantLink::TYPE_USER )
            {
                $participant->addUser(
                    $this->repository->getUserService()->loadUser(
                        $contentObject->attribute( 'id' )
                    )
                );
            }
            elseif ( $participantLink->attribute( 'participant_type' ) == eZCollaborationItemParticipantLink::TYPE_USERGROUP )
            {
                /** @var \eZContentObjectTreeNode $child */
                foreach ( $contentObject->mainNode()->children() as $child )
                {
                    $participant->addUser(
                        $this->repository->getUserService()->loadUser(
                            $child->attribute( 'contentobject_id' )
                        )
                    );
                }
            }
        }

        return $participant;
    }

    /**
     * @param int $userId
     *
     * @return eZCollaborationGroup|null
     */
    protected function getMainCollaborationGroup( $userId )
    {
        return $this->getCollaborationGroup( self::MAIN_COLLABORATION_GROUP_NAME, $userId );
    }

    /**
     * @param int $userId
     *
     * @return eZCollaborationGroup|null
     */
    protected function getTrashCollaborationGroup( $userId )
    {
        return $this->getCollaborationGroup( self::TRASH_COLLABORATION_GROUP_NAME, $userId );
    }

    /**
     * @param string $groupName
     * @param int $userId
     *
     * @return eZCollaborationGroup|null
     */
    protected function getCollaborationGroup( $groupName, $userId )
    {
        $group = eZPersistentObject::fetchObject(
            eZCollaborationGroup::definition(),
            null,
            array(
                'user_id' => $userId,
                'title' => $groupName
            )
        );
        if ( !$group instanceof eZCollaborationGroup && $groupName != '' )
        {
            $group = eZCollaborationGroup::instantiate(
                $userId,
                $groupName
            );
        }
        return $group;
    }

}