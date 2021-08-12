<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanForceFix extends UserIs
{
    public $identifier = 'can_force_fix';

    public function userHasPermission(User $user, Post $post)
    {
        return (
                ($this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
                    && !$this->userIs(ParticipantRole::ROLE_OWNER, $user, $post))
                ||
                ($this->userGroupIs(ParticipantRole::ROLE_OWNER, $user, $post)
                    && !empty($post->owners->getParticipantIdListByType(Participant::TYPE_USER))
                    && !$this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post))
            )
            && $post->workflowStatus->is(Post\WorkflowStatus::ASSIGNED);
    }
}