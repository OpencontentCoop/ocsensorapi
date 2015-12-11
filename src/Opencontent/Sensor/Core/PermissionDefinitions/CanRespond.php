<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class CanRespond extends PermissionDefinition
{
    public $identifier = 'can_respond';

    public function userHasPermission( User $user, Post $post )
    {
        return (
            !$post->workflowStatus->is( Post\WorkflowStatus::CLOSED )
            && !$post->workflowStatus->is( Post\WorkflowStatus::ASSIGNED )
            && $post->approvers->getParticipantById($user->id) instanceof Participant
        );
    }
}