<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanFix extends UserIs
{
    public $identifier = 'can_fix';

    public function userHasPermission(User $user, Post $post)
    {        
        return $this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post)
            && $post->workflowStatus->is(Post\WorkflowStatus::ASSIGNED);
    }
}