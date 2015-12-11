<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Message\CommentStruct;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class AddCommentAction extends ActionDefinition
{

    public function __construct()
    {
        $this->identifier = 'add_comment';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_comment' );
        $this->inputName = 'Comment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $parameter->inputName = 'SensorItemComment';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $text = $action->getParameterValue( 'text' );

        $commentStruct = new CommentStruct();
        $commentStruct->createdDateTime = new \DateTime();
        $commentStruct->creator = $repository->getCurrentUser();
        $commentStruct->post = $post;
        $commentStruct->text = $text;

        $repository->getMessageService()->createComment( $commentStruct );
        $repository->getPostService()->refreshPost( $post );
        $this->fireEvent( $repository, $post, $user, array( 'text' => $text ) );

        if ( $post->workflowStatus->is( Post\WorkflowStatus::CLOSED )
             && $post->author->getUserById( $user->id )
             && $repository->getSensorSettings()->get( 'AuthorCanReopen' ) )
        {
            $action = new Action();
            $action->identifier = 'reopen';
            $repository->getActionService()->runAction( $action, $post );
        }
    }
}
