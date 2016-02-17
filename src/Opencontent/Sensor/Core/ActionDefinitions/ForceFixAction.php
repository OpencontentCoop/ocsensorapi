<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class ForceFixAction extends ActionDefinition
{
    public $identifier = 'force_fix';

    public $permissionDefinitionIdentifiers = array( 'can_read', 'can_force_fix' );

    public $inputName = 'ForceFix';

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleObserver = $roles->getParticipantRoleById( ParticipantRole::ROLE_OBSERVER );
        foreach( $post->owners as $owner )
        {
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $owner->id,
                $roleObserver
            );
        }

        $repository->getPostService()->setPostWorkflowStatus( $post, Post\WorkflowStatus::FIXED );
        $repository->getMessageService()->addTimelineItemByWorkflowStatus( $post, Post\WorkflowStatus::FIXED );
        $this->fireEvent( $repository, $post, $user );
    }
}
