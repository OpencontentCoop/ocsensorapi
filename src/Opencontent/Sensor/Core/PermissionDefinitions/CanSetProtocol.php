<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanSetProtocol extends UserIs
{
    public $identifier = 'can_set_protocol';

    public function userHasPermission(User $user, Post $post)
    {
        return $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post);
    }
}