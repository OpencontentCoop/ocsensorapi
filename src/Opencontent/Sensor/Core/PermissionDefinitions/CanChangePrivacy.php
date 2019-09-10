<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanChangePrivacy extends UserIs
{
    public $identifier = 'can_change_privacy';

    public function userHasPermission(User $user, Post $post)
    {
        return $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post);
    }
}