<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Core\UserService as UserServiceBase;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Legacy\Values\User as User;
use eZUser;
use eZCollaborationItemStatus;
use SocialUser;
use eZCollaborationItemParticipantLink;

class UserService extends UserServiceBase
{
    /**
     * @var User[]
     */
    protected $users = array();

    public function loadUser( $id )
    {
        if ( !isset( $this->users[$id] ) )
        {
            $user = new User();
            $user->id = $id;
            $ezUser = $this->getEzUser( $id );
            if ( $ezUser instanceof eZUser )
            {
                $user->email = $ezUser->Email;
                $user->name = $ezUser->contentObject()->name( false, $this->repository->getCurrentLanguage() );
                $user->isEnabled = $ezUser->isEnabled();
                $socialUser = SocialUser::instance($ezUser);
                $user->commentMode = !$socialUser->hasDenyCommentMode();
                $user->moderationMode = $socialUser->hasModerationMode();
            }
            $this->users[$id] = $user;
        }
        return $this->users[$id];
    }

    public function refreshUser( \OpenContent\Sensor\Api\Values\User $user )
    {

    }

    public function setUserPostAware( $user, Post $post )
    {
        if ( is_numeric( $user ) )
            $user = $this->loadUser( $user );

        $itemStatus = eZCollaborationItemStatus::fetch( $post->internalId, $user->id );
        if ( $itemStatus instanceof eZCollaborationItemStatus )
        {
            $user->lastAccessDateTime = Utils::getDateTimeFromTimestamp( $itemStatus->attribute( 'last_read' ) );
        }
        $user->permissions = $this->repository->getPermissionService()->loadUserPostPermissionCollection( $user, $post );
        return $user;
    }

    public function setBlockMode( \OpenContent\Sensor\Api\Values\User $user, $enable = true )
    {
        $socialUser = SocialUser::instance($this->loadUser($user->id)->ezUser);
        $socialUser->setBlockMode($enable);
        $user->isEnabled = $enable;
        $this->refreshUser( $user );
    }

    public function setCommentMode( \OpenContent\Sensor\Api\Values\User $user, $enable = true )
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        $socialUser->setDenyCommentMode(!$enable);
        $user->commentMode = $enable;
        $this->refreshUser( $user );
    }

    public function setBehalfOfMode( \OpenContent\Sensor\Api\Values\User $user, $enable = true )
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        $socialUser->setCanBehalfOfMode($enable);
        $user->behalfOfMode = $enable;
        $this->refreshUser( $user );
    }

    public function getAlerts( \OpenContent\Sensor\Api\Values\User $user )
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        return $socialUser->attribute( 'alerts' );
    }

    public function addAlerts( \OpenContent\Sensor\Api\Values\User $user, $message, $level )
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        $socialUser->addFlashAlert($message, $level);
    }

    protected function getEzUser( $id ){
        $user = eZUser::fetch( $id );
        if ( !$user instanceof eZUser )
            $user = new eZUser( array() );
        return $user;
    }

    public function setLastAccessDateTime( \OpenContent\Sensor\Api\Values\User $user, Post $post )
    {
        $timestamp = time();
        eZCollaborationItemStatus::setLastRead( $post->internalId, $user->id, $timestamp );
        eZCollaborationItemParticipantLink::setLastRead( $post->internalId, $user->id, $timestamp );
    }
}