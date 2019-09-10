<?php

namespace Opencontent\Sensor\Api\Exception;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\Post;

class PermissionException extends BaseException
{
    public function __construct($permissionDefinitionIdentifier, User $user, Post $post)
    {
        $message = "Ungranted permission {$permissionDefinitionIdentifier} for user {$user->name} in post #{$post->id}";
        parent::__construct($message);
    }

    public function getServerErrorCode()
    {
        return 403;
    }
}