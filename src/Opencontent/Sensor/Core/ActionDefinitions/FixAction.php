<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class FixAction extends ActionDefinition
{
    public $identifier = 'fix';

    public $permissionDefinitionIdentifiers = array( 'can_read', 'can_fix' );

    public $inputName = 'Fix';

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        if ( $post->owners->getParticipantById( $user->id ) )
        {
            $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
            $roleObserver = $roles->getParticipantRoleById( ParticipantRole::ROLE_OBSERVER );
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $user->id,
                $roleObserver
            );
        }
        $repository->getMessageService()->addTimelineItemByWorkflowStatus( $post, Post\WorkflowStatus::FIXED );
        if ( $repository->getParticipantService()->loadPostParticipantsByRole( $post, ParticipantRole::ROLE_OWNER )->count() == 0 )
        {
            $repository->getPostService()->setPostWorkflowStatus( $post, Post\WorkflowStatus::FIXED );
        }
        $this->fireEvent( $repository, $post, $user );
    }
}
