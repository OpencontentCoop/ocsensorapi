<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanRemoveImage extends UserIs
{
    public $identifier = 'can_remove_image';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && count($post->images) > 0
            && $this->userIs(ParticipantRole::ROLE_AUTHOR, $user, $post);
    }
}