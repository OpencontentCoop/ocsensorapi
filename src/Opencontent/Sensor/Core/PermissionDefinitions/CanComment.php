<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class CanComment extends PermissionDefinition
{
    public $identifier = 'can_comment';

    public function userHasPermission( User $user, Post $post )
    {
        return $user->commentMode && !$user->moderationMode && $post->commentsIsOpen;
    }
}