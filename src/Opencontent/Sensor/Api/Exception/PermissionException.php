<?php

namespace OpenContent\Sensor\Api\Exception;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\Post;

class PermissionException extends BaseException
{
    public function __construct( $permissionDefinitionIdentifier, User $user, Post $post )
    {
        $message = "Ungranted permission {$permissionDefinitionIdentifier} for user {$user->name} in post #{$post->id}";
        parent::__construct( $message );
    }
}