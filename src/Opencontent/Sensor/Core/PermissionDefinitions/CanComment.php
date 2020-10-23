<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanComment extends PermissionDefinition
{
    public $identifier = 'can_comment';

    public function userHasPermission(User $user, Post $post)
    {
        return $user->commentMode && !$user->moderationMode && $post->commentsIsOpen;
    }
}