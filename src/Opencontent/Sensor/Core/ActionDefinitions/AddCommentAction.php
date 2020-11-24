<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddCommentAction extends ActionDefinition
{

    public function __construct()
    {
        $this->identifier = 'add_comment';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_comment');
        $this->inputName = 'Comment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $text = $action->getParameterValue('text');
        if (trim($text) == ''){
            throw new InvalidInputException("Text is required");
        }

        $commentStruct = new CommentStruct();
        $commentStruct->createdDateTime = new \DateTime();
        $commentStruct->creator = $repository->getCurrentUser();
        $commentStruct->post = $post;
        $commentStruct->text = $text;

        $repository->getMessageService()->createComment($commentStruct);
        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, array('text' => $text));

        if ($post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && $post->author->id == $user->id
            && $repository->getSensorSettings()->get('AuthorCanReopen')) {
            $action = new Action();
            $action->identifier = 'reopen';
            $repository->getActionService()->runAction($action, $post);
        }
    }
}
