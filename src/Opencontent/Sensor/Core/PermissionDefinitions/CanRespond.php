<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanRespond extends UserIs
{
    public $identifier = 'can_respond';

    public function userHasPermission(User $user, Post $post)
    {
        return (
            !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && (!$post->workflowStatus->is(Post\WorkflowStatus::ASSIGNED) || $this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post))
            && $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
        );
    }
}