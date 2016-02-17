<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

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