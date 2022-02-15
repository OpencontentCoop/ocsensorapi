<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanRemoveFile extends UserIs
{
    public $identifier = 'can_remove_file';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && count($post->files) > 0
            && $this->userIs(ParticipantRole::ROLE_AUTHOR, $user, $post);
    }
}
