<?php

namespace Opencontent\Sensor\Legacy\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanEdit extends PermissionDefinition
{
    public $identifier = 'can_edit';

    public function userHasPermission( User $user, Post $post )
    {
        //@todo
        return true;
    }
}