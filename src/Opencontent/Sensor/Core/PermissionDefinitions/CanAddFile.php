<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanAddFile extends UserIs
{
    public $identifier = 'can_add_file';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && count($post->files) < $this->getMaxNumberOfFiles()
            && $this->userIs(ParticipantRole::ROLE_AUTHOR, $user, $post);
    }

    protected function getMaxNumberOfFiles()
    {
        return -1;
    }
}
