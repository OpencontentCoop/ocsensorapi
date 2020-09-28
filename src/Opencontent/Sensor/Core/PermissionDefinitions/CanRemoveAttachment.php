<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;


class CanRemoveAttachment extends UserIs
{
    public $identifier = 'can_remove_attachment';

    public function userHasPermission(User $user, Post $post)
    {
        return $this->participantIs(ParticipantRole::ROLE_OWNER, $user, $post)
            || $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post);
    }
}