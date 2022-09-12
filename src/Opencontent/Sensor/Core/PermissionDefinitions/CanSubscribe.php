<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanSubscribe extends UserIs
{
    public $identifier = 'can_subscribe';

    public function userHasPermission(User $user, Post $post)
    {
        return $user->commentMode && !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED);
    }
}