<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

interface UserService
{
    /**
     * @param $id
     * @return User
     * @throw \Exception
     */
    public function loadUser($id);

    /**
     * @param mixed $user
     * @param Post $post
     *
     * @return User
     */
    public function setUserPostAware($user, Post $post);

    public function setBlockMode(User $user, $enable = true);

    public function setCommentMode(User $user, $enable = true);

    public function setBehalfOfMode(User $user, $enable = true);

    public function setModerationMode(User $user, $enable = true);

    public function getAlerts(User $user);

    public function addAlert(User $user, $message, $level);

    public function setLastAccessDateTime(User $user, Post $post);

    public function refreshUser($user);

    public function setAsSuperObserver(User $user, $enable = true);
}
