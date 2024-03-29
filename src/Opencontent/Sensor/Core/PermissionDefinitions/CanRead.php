<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanRead extends PermissionDefinition implements ReadOnlyAllowedInterface
{
    public $identifier = 'can_read';

    public function userHasPermission(User $user, Post $post)
    {
        if ($post->privacy->identifier == 'private' || $post->moderation->identifier == 'waiting' || $post->moderation->identifier == 'refused') {
            return $post->participants->getUserById($user->id);
        }

        return true;
    }
}