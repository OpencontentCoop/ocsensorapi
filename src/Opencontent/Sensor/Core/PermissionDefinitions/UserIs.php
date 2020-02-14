<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

abstract class UserIs extends PermissionDefinition
{
    /**
     * @param $roleId
     * @param User $user
     * @param Post $post
     *
     * @return bool|User
     */
    public function userIs($roleId, User $user, Post $post)
    {
        $collection = $post->participants->getParticipantsByRole($roleId);
        return $collection->getUserById($user->id);
    }

    public function participantIs($roleId, User $user, Post $post)
    {
        $collection = $post->participants->getParticipantsByRole($roleId);
        foreach ($collection as $participant){
            if ($participant->id == $user->id){
                return true;
            }
        }

        return false;
    }
}