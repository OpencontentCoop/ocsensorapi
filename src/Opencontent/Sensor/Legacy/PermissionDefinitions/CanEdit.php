<?php

namespace OpenContent\Sensor\Legacy\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class CanEdit extends PermissionDefinition
{
    public $identifier = 'can_edit';

    public function userHasPermission( User $user, Post $post )
    {
        //@todo
        return true;
    }
}