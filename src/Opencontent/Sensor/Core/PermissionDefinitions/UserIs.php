<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantCollection;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

abstract class UserIs extends PermissionDefinition
{
    /**
     * Verifica se $user o il gruppo di $user partecipa con il ruolo $roleId
     * @param $roleId
     * @param User $user
     * @param Post $post
     *
     * @return bool|User
     */
    public function userIs($roleId, User $user, Post $post)
    {
        $collection = $post->participants->getParticipantsByRole($roleId);
        $userHasRole = $collection->getUserById($user->id);
        if ($roleId === ParticipantRole::ROLE_AUTHOR && $user->id == $post->reporter->id){
            $userHasRole = true;
        }
        return $userHasRole;
    }

    /**
     * Verifica se $user partecipa con il ruolo $roleId
     * @param $roleId
     * @param User $user
     * @param Post $post
     * @return bool
     */
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
    /**
     * Verifica se il gruppo $user partecipa con il ruolo $roleId
     * @param $roleId
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function userGroupIs($roleId, User $user, Post $post)
    {
        $collection = $post->participants->getParticipantsByRole($roleId);
        /** @var Participant $participant */
        foreach ($collection as $participant){
            if ($participant->type == Participant::TYPE_GROUP){
                return $participant->getUserById($user->id);
            }
        }

        return false;
    }
}