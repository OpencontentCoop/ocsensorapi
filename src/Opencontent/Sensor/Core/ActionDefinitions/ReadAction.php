<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class ReadAction extends ActionDefinition
{
    public $identifier = 'read';

    public $permissionDefinitionIdentifiers = array( 'can_read' );

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $repository->getUserService()->setLastAccessDateTime( $user, $post );
        if ( $post->approvers->getUserById( $user->id ) instanceof User
             && ( $post->workflowStatus->is( Post\WorkflowStatus::WAITING )
                  || $post->workflowStatus->is( Post\WorkflowStatus::REOPENED ) ) )
        {
            $repository->getPostService()->setPostWorkflowStatus( $post, Post\WorkflowStatus::READ );
            $repository->getMessageService()->addTimelineItemByWorkflowStatus( $post, Post\WorkflowStatus::READ );
            $this->fireEvent( $repository, $post, $user );
        }
    }
}

