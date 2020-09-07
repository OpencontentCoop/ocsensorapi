<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanRemoveObserver extends UserIs
{
    public $identifier = 'can_remove_observer';

    public function userHasPermission(User $user, Post $post)
    {
        return $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post);
    }
}