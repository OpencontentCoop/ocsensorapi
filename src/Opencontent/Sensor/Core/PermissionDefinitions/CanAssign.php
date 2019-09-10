<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanAssign extends UserIs
{
    public $identifier = 'can_assign';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && ($this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
                || ($this->userIs(ParticipantRole::ROLE_OWNER, $user, $post) && $post->workflowStatus->is(Post\WorkflowStatus::ASSIGNED)));
    }
}