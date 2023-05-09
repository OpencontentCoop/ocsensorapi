<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanReadUnmoderatedComment extends UserIs implements ReadOnlyAllowedInterface
{
    public $identifier = 'can_read_unmoderated_comment';

    public function userHasPermission(User $user, Post $post)
    {
        return $this->userIs(ParticipantRole::ROLE_APPROVER, $user, $post);
    }
}