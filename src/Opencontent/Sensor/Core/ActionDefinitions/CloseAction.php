<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;


class CloseAction extends ActionDefinition
{
    public $identifier = 'close';

    public $permissionDefinitionIdentifiers = array( 'can_read', 'can_close' );

    public $inputName = 'Close';

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $repository->getPostService()->setPostWorkflowStatus( $post, Post\WorkflowStatus::CLOSED );
        $repository->getMessageService()->addTimelineItemByWorkflowStatus( $post, Post\WorkflowStatus::CLOSED );
        $this->fireEvent( $repository, $post, $user );
    }
}
