<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\ParticipantRole;

class CanReopen extends UserIs
{
    public $identifier = 'can_reopen';

    protected $isEnabled;

    public function __construct( $isEnabled )
    {
        $this->isEnabled = $isEnabled;
    }

    public function userHasPermission( User $user, Post $post )
    {
        return $this->isEnabled
               && ( $this->userIs( ParticipantRole::ROLE_APPROVER, $user, $post )
                 || $this->userIs( ParticipantRole::ROLE_AUTHOR, $user, $post ) )
               && $post->workflowStatus->is( Post\WorkflowStatus::CLOSED );
    }
}