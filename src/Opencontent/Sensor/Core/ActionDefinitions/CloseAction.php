<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;


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
