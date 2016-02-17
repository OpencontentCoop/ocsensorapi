<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;


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
