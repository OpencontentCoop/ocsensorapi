<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanSetType extends UserIs
{
    public $identifier = 'can_set_type';

    public function userHasPermission(User $user, Post $post)
    {
        return $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
            && !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED);
    }
}
