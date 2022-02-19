<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanSetTags extends PermissionDefinition
{
    public $identifier = 'can_set_tags';

    public function userHasPermission(User $user, Post $post)
    {
        return $user->isFirstApprover;
    }

}
