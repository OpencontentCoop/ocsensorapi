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
        $this->permissionDefinitionIdentifiers = ['can_read', 'can_comment'];
        $this->inputName = 'Comment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'creator_id';
        $parameter->isRequired = false;
        $parameter->type = 'integer';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'external_id';
        $parameter->isRequired = false;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $text = $action->getParameterValue('text');
        if (trim($text) == '') {
            throw new InvalidInputException("Text is required");
        }

        $creator = $repository->getCurrentUser();
        $requestCreatorId = (int)$action->getParameterValue('creator_id');
        if ($requestCreatorId > 0
            && $requestCreatorId != $repository->getCurrentUser()->id
            && $repository->getCurrentUser()->behalfOfMode) {
            $creator = $repository->getUserService()->loadUser($requestCreatorId);
        }

        $commentStruct = new CommentStruct();
        $commentStruct->createdDateTime = new \DateTime();
        $commentStruct->creator = $creator;
        $commentStruct->post = $post;
        $commentStruct->text = $text;
        $commentStruct->externalId = $action->getParameterValue('external_id');
        if ($creator->moderationMode) {
            $commentStruct->needModeration = true;
        }

        $repository->getMessageService()->createComment($commentStruct);
        $post = $repository->getPostService()->refreshPost($post);
        if ($commentStruct->needModeration) {
            $this->fireEvent($repository, $post, $user, ['text' => $text], 'on_add_comment_to_moderate');
        } else {
            $this->fireEvent($repository, $post, $user, ['text' => $text]);
        }

        if ($post->workflowStatus->is(Post\WorkflowStatus::CLOSED)
            && $post->author->id == $user->id
            && $repository->getSensorSettings()->get('AuthorCanReopen')) {
            $action = new Action();
            $action->identifier = 'reopen';
            $repository->getActionService()->runAction($action, $post);
        }
    }
}
