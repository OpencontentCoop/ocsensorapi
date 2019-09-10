<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;


class CanRemoveAttachment extends PermissionDefinition
{
    public $identifier = 'can_remove_attachment';

    public function userHasPermission(User $user, Post $post)
    {
        return $post->owners->getParticipantById($user->id) instanceof Participant
            || $post->approvers->getParticipantById($user->id) instanceof Participant;
    }
}