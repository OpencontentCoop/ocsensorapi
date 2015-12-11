<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class AddObserverAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_observer';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_add_observer' );
        $this->inputName = 'AddObserver';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorItemAddObserver';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $participantIds = (array) $action->getParameterValue('participant_ids');
        $isChanged = false;

        $currentApproverIds = $post->approvers->getParticipantIdList();
        $currentOwnerIds = $post->owners->getParticipantIdList();
        $currentObserverIds = $post->observers->getParticipantIdList();;
        $makeObserverIds = array_diff( $participantIds, $currentObserverIds, $currentApproverIds, $currentOwnerIds );

        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleObserver = $roles->getParticipantRoleById( ParticipantRole::ROLE_OBSERVER );

        foreach ( $makeObserverIds as $id )
        {
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $id,
                $roleObserver
            );
            $isChanged = true;
        }

        if ( $isChanged )
        {
            $repository->getPostService()->refreshPost( $post );
            $this->fireEvent( $repository, $post, $user, array( 'observers' => $makeObserverIds ) );
        }
    }
}