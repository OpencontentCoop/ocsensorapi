<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanAddObserver extends UserIs
{
    public $identifier = 'can_add_observer';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && ($this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post) || $this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post));
    }
}