<?php

namespace OpenContent\Sensor\Legacy\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class CanRemove extends PermissionDefinition
{
    public $identifier = 'can_remove';

    public function userHasPermission( User $user, Post $post )
    {
        //@todo
        return true;
    }
}