<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;


class AssignAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'assign';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_assign' );
        $this->inputName = 'Assign';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorItemAssignTo';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $isChanged = false;
        $allowMultipleOwner = $repository->getSensorSettings()->get( 'AllowMultipleOwner' );

        $participantIds = (array) $action->getParameterValue('participant_ids');
        $currentApproverIds = $post->approvers->getParticipantIdList();
        $currentOwnerIds = $post->owners->getParticipantIdList();
        $makeOwnerIds = array_diff( $participantIds, $currentOwnerIds, $currentApproverIds );
        $makeObserverIds = array_diff( $currentOwnerIds, $participantIds );

        if ( $makeObserverIds == $currentOwnerIds && empty( $makeOwnerIds  ) )
        {
            return;
        }

        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleOwner = $roles->getParticipantRoleById( ParticipantRole::ROLE_OWNER );
        $roleObserver = $roles->getParticipantRoleById( ParticipantRole::ROLE_OBSERVER );

        if ( !$allowMultipleOwner && count( $makeOwnerIds ) > 1 )
        {
            $makeOwnerId = array_shift( $makeOwnerIds );
            $makeObserverIds = array_unique( array_merge( $makeObserverIds, $makeOwnerIds ) );
            $makeOwnerIds = array( $makeOwnerId );
        }

        foreach( $makeOwnerIds as $id )
        {
            $repository->getParticipantService()->addPostParticipant( $post, $id, $roleOwner );
            $isChanged = true;
        }

        if ( $isChanged )
        {
            if ( !$allowMultipleOwner )
            {
                foreach ( $makeObserverIds as $id )
                {
                    $repository->getParticipantService()->addPostParticipant(
                        $post,
                        $id,
                        $roleObserver
                    );
                }
            }
            $repository->getPostService()->setPostWorkflowStatus( $post, Post\WorkflowStatus::ASSIGNED );
            $repository->getMessageService()->addTimelineItemByWorkflowStatus( $post, Post\WorkflowStatus::ASSIGNED, $makeOwnerIds );
            $this->fireEvent( $repository, $post, $user, array( 'owners' => $makeOwnerIds ) );
        }
    }
}
