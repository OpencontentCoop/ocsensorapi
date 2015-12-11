<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

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
