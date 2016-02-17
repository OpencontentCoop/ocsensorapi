<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanSendPrivateMessage extends UserIs
{
    public $identifier = 'can_send_private_message';

    public function userHasPermission( User $user, Post $post )
    {
        return $this->userIs( ParticipantRole::ROLE_OWNER, $user, $post )
               || $this->userIs( ParticipantRole::ROLE_OBSERVER, $user, $post )
               || $this->userIs( ParticipantRole::ROLE_APPROVER, $user, $post );
    }
}