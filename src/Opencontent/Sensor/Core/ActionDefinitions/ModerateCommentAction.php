<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class ModerateCommentAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'moderate_comment';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_moderate_comment');
        $this->inputName = 'ModerateComment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'comment_id';
        $parameter->type = 'integer';
        $parameter->isRequired = true;
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $commentId = $action->getParameterValue('comment_id');
        foreach ($post->comments->messages as $message){
            if ($message->id == $commentId){

                $commentStruct = new CommentStruct();
                $commentStruct->id = $message->id;
                $commentStruct->text = $message->text;
                $commentStruct->creator = $message->creator;
                $commentStruct->needModeration = false;

                $repository->getMessageService()->updateComment($commentStruct);
                $post = $repository->getPostService()->refreshPost($post);
                $this->fireEvent($repository, $post, $user, array('text' => $message->text), 'on_add_comment');
            }
        }
    }
}