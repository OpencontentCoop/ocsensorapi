<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\ParticipantRole;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;


class ReopenAction extends ActionDefinition
{
    public $identifier = 'reopen';

    public $permissionDefinitionIdentifiers = array( 'can_read', 'can_reopen' );

    public $inputName = 'Reopen';

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $repository->getPostService()->setPostWorkflowStatus( $post, Post\WorkflowStatus::REOPENED );
        $repository->getMessageService()->addTimelineItemByWorkflowStatus( $post, Post\WorkflowStatus::REOPENED );
        $this->fireEvent( $repository, $post, $user );
    }
}
