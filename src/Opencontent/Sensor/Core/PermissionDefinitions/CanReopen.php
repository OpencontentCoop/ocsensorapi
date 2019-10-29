<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanReopen extends UserIs
{
    public $identifier = 'can_reopen';

    private $approverEnabled;

    private $authorEnabled;

    public function __construct($approverEnabled, $authorEnabled)
    {
        $this->approverEnabled = $approverEnabled;
        $this->authorEnabled= $authorEnabled;
    }

    public function userHasPermission(User $user, Post $post)
    {
        return (($this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post) && $this->approverEnabled)
                || ($this->userIs(ParticipantRole::ROLE_AUTHOR, $user, $post)) && $this->authorEnabled)
            && $post->workflowStatus->is(Post\WorkflowStatus::CLOSED);
    }
}