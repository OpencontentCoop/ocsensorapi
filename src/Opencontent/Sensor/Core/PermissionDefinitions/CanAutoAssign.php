<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanAutoAssign extends UserIs
{
    public $identifier = 'can_auto_assign';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && !$this->participantIs(ParticipantRole::ROLE_APPROVER, $user, $post)
            && (
                ($this->userGroupIs(ParticipantRole::ROLE_OWNER, $user, $post)
                    && !$this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post))
                ||
                ($this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
                    && !($this->userIs(ParticipantRole::ROLE_OWNER, $user, $post) && $post->workflowStatus->is(Post\WorkflowStatus::ASSIGNED)))
            );
    }
}