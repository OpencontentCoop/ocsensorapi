<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
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
        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'moderation';
        $parameter->type = 'string';
        $parameter->isRequired = false;
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $commentId = $action->getParameterValue('comment_id');
        $moderation = 'approve';
        if ($action->hasParameter('moderation')){
            $moderation = $action->getParameterValue('moderation');
        }
        if (!in_array($moderation, ['approve', 'reject'])){
            throw new InvalidArgumentException("Moderation $moderation unhandled");
        }
        foreach ($post->comments->messages as $message){
            if ($message->id == $commentId){

                $commentStruct = new CommentStruct();
                $commentStruct->id = $message->id;
                $commentStruct->text = $message->text;
                $commentStruct->creator = $message->creator;
                $commentStruct->needModeration = false;
                $commentStruct->isRejected = $moderation == 'reject';

                $repository->getMessageService()->updateComment($commentStruct);

                $auditStruct = new AuditStruct();
                $auditStruct->createdDateTime = new \DateTime();
                $auditStruct->creator = $user;
                $auditStruct->post = $post;
                $auditStruct->text = "Impostata moderazione commento #{$message->id} a {$moderation}";
                $repository->getMessageService()->createAudit($auditStruct);

                $post = $repository->getPostService()->refreshPost($post);
                $this->fireEvent($repository, $post, $user, array('text' => $message->text), 'on_add_comment');
            }
        }
    }
}