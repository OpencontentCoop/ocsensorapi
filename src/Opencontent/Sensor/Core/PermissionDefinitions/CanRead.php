<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class CanRead extends PermissionDefinition
{
    public $identifier = 'can_read';

    public function userHasPermission( User $user, Post $post )
    {
        if ( $post->privacy->identifier == 'private' || $post->moderation->identifier == 'waiting' || $post->moderation->identifier == 'refused' ) {
            return $post->participants->getUserById( $user->id );
        }

        return true;
    }
}