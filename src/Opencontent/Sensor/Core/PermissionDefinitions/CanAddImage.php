<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanAddImage extends UserIs
{
    public $identifier = 'can_add_image';

    public function userHasPermission(User $user, Post $post)
    {
        return !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && count($post->images) < $this->getMaxNumberOfImages()
            && $this->userIs(ParticipantRole::ROLE_AUTHOR, $user, $post);
    }

    protected function getMaxNumberOfImages()
    {
        return -1;
    }
}
