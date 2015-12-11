<?php

namespace OpenContent\Sensor\Core\PermissionDefinitions;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Participant;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

abstract class UserIs extends PermissionDefinition
{
    /**
     * @param $roleId
     * @param User $user
     * @param Post $post
     *
     * @return bool|User
     */
    public function userIs( $roleId, User $user, Post $post )
    {
        $collection = $post->participants->getParticipantsByRole( $roleId );
        return $collection->getUserById( $user->id );
    }
}