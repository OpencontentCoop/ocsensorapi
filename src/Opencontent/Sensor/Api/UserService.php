<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

interface UserService
{
    /**
     * @param $id
     *
     * @return User
     */
    public function loadUser( $id );

    /**
     * @param mixed $id
     * @param Post $post
     *
     * @return User
     */
    public function setUserPostAware( $id, Post $post );

    public function setBlockMode( User $user, $enable = true );

    public function setCommentMode( User $user, $enable = true );

    public function setBehalfOfMode( User $user, $enable = true );

    public function getAlerts( User $user );

    public function addAlerts( User $user, $message, $level );

    public function setLastAccessDateTime( User $user, Post $post );

    public function refreshUser( User $user );

}
