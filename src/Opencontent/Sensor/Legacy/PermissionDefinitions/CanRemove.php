<?php

namespace Opencontent\Sensor\Legacy\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanRemove extends PermissionDefinition
{
    public $identifier = 'can_remove';

    public function userHasPermission( User $user, Post $post )
    {
        //@todo
        return true;
    }
}