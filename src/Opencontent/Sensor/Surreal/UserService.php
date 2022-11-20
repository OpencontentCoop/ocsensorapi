<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\UserService as CoreUserService;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class UserService extends CoreUserService
{

    /**
     * @inheritDoc
     */
    public function loadUser($id)
    {
        // TODO: Implement loadUser() method.
    }

    /**
     * @inheritDoc
     */
    public function setUserPostAware($user, Post $post)
    {
        // TODO: Implement setUserPostAware() method.
    }

    public function setBlockMode(User $user, $enable = true)
    {
        // TODO: Implement setBlockMode() method.
    }

    public function setCommentMode(User $user, $enable = true)
    {
        // TODO: Implement setCommentMode() method.
    }

    public function setBehalfOfMode(User $user, $enable = true)
    {
        // TODO: Implement setBehalfOfMode() method.
    }

    public function setModerationMode(User $user, $enable = true)
    {
        // TODO: Implement setModerationMode() method.
    }

    public function getAlerts(User $user)
    {
        // TODO: Implement getAlerts() method.
    }

    public function addAlert(User $user, $message, $level)
    {
        // TODO: Implement addAlert() method.
    }

    public function setLastAccessDateTime(User $user, Post $post)
    {
        // TODO: Implement setLastAccessDateTime() method.
    }

    public function refreshUser($user)
    {
        // TODO: Implement refreshUser() method.
    }

    public function setAsSuperObserver(User $user, $enable = true)
    {
        // TODO: Implement setAsSuperObserver() method.
    }

    public function loadUsers($query, $limit, $cursor)
    {
        // TODO: Implement loadUsers() method.
    }

    public function createUser(array $payload, $ignorePolicies = false)
    {
        // TODO: Implement createUser() method.
    }

    public function updateUser(User $user, $payload)
    {
        // TODO: Implement updateUser() method.
    }

    public function setRestrictMode(User $user, $enable = true)
    {
        // TODO: Implement setRestrictMode() method.
    }
}