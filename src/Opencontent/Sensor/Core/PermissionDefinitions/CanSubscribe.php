<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanSubscribe extends UserIs
{
    public $identifier = 'can_subscribe';

    private $currentUserId;

    public function __construct($currentUserId)
    {
        $this->currentUserId = (int)$currentUserId;
    }

    public function userHasPermission(User $user, Post $post)
    {
        return intval($post->author->id) === $this->currentUserId
            && !$post->workflowStatus->is(Post\WorkflowStatus::CLOSED);
    }
}