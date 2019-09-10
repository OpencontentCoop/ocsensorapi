<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanAddApprover extends UserIs
{
    public $identifier = 'can_add_approver';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED) && $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post);
    }
}