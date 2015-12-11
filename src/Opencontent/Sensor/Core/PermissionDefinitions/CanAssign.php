<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\ParticipantRole;

class CanAssign extends UserIs
{
    public $identifier = 'can_assign';

    public function userHasPermission( User $user, Post $post )
    {
        return !$post->workflowStatus->is( Post\WorkflowStatus::CLOSED )
               && ( $this->userIs( ParticipantRole::ROLE_APPROVER, $user, $post )
                    || ( $this->userIs( ParticipantRole::ROLE_OWNER, $user, $post ) && $post->workflowStatus->is( Post\WorkflowStatus::ASSIGNED ) ) );
    }
}