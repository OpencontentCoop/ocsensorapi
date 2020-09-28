<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanAddAttachment extends UserIs
{
    public $identifier = 'can_add_attachment';

    public function userHasPermission(User $user, Post $post)
    {
        return
            !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && ( $this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post)
                || $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post) );
    }
}