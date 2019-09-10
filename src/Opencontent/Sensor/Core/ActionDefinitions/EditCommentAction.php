<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\UnauthorizedException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class EditCommentAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'edit_comment';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_comment');
        $this->inputName = 'EditComment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'id';
        $parameter->isRequired = true;
        $parameter->type = 'integer';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $commentStruct = new CommentStruct();
        $commentStruct->id = $action->getParameterValue('id');
        $commentStruct->text = $action->getParameterValue('text');
        $commentStruct->creator = $user;

        /** @var Message $comment */
        foreach ($post->comments as $comment) {
            if ($comment->id == $commentStruct->id && $comment->creator->id == $user->id) {
                $repository->getMessageService()->updateComment($commentStruct);
                $post = $repository->getPostService()->refreshPost($post);
                $this->fireEvent($repository, $post, $user, array('message' => $commentStruct->text));
                return;
            }
        }

        throw new UnauthorizedException("Current user can not edit this comment");
    }
}