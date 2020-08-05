<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanClose extends UserIs
{
    public $identifier = 'can_close';

    private $restrictResponders;

    public function __construct($restrictResponders = [])
    {
        $this->restrictResponders = $restrictResponders;
    }

    public function userHasPermission(User $user, Post $post)
    {
        if (!empty($this->restrictResponders)){
            return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
                && $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
                && in_array($user->id, $this->restrictResponders);
        }

        return $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post)
            && !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && (!$post->workflowStatus->is(Post\WorkflowStatus::ASSIGNED) || $this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post));
    }
}