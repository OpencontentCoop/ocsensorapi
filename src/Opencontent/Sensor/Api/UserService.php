<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 24/11/15
 * Time: 12:48
 */

namespace OpenContent\Sensor\Api;

use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

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
