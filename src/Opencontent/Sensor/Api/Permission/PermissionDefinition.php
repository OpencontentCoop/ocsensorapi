<?php

namespace Opencontent\Sensor\Api\Permission;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

abstract class PermissionDefinition
{
    public $identifier;

    /**
     * @param User $user
     * @param Post $post
     *
     * @return bool
     */
    abstract public function userHasPermission(User $user, Post $post);
}