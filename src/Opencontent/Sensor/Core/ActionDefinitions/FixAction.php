<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

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
