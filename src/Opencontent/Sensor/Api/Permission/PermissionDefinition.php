<?php

namespace OpenContent\Sensor\Api\Permission;

use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

abstract class PermissionDefinition
{
    public $identifier;

    /**
     * @param User $user
     * @param Post $post
     *
     * @return bool
     */
    abstract public function userHasPermission( User $user, Post $post );
}