<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\ParticipantRole;

class CanAddObserver extends UserIs
{
    public $identifier = 'can_add_observer';

    public function userHasPermission( User $user, Post $post )
    {
        return !$post->workflowStatus->is( Post\WorkflowStatus::CLOSED )
               && ( $this->userIs( ParticipantRole::ROLE_APPROVER, $user, $post ) || $this->userIs( ParticipantRole::ROLE_OWNER, $user, $post ) );
    }
}