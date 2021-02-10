<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Message\PrivateMessage;
use Opencontent\Sensor\Api\Values\Message\PrivateMessageStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Exception\UnauthorizedException;

class EditPrivateMessageAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'edit_message';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_send_private_message');
        $this->inputName = 'EditMessage';

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
        $messageStruct = new PrivateMessageStruct();
        $messageStruct->id = $action->getParameterValue('id');
        $messageStruct->text = $action->getParameterValue('text');
        $messageStruct->creator = $user;

        /** @var PrivateMessage[] $comment */
        foreach ($post->privateMessages as $privateMessage) {
            if ($privateMessage->id == $messageStruct->id && $privateMessage->creator->id == $user->id) {
                $oldText = $privateMessage->text;
                $repository->getMessageService()->updatePrivateMessage($messageStruct);

                $auditStruct = new AuditStruct();
                $auditStruct->createdDateTime = new \DateTime();
                $auditStruct->creator = $user;
                $auditStruct->post = $post;
                $auditStruct->text = "Modificato messaggio privato #{$privateMessage->id}, il testo precedente era: {$oldText}";
                $repository->getMessageService()->createAudit($auditStruct);

                $post = $repository->getPostService()->refreshPost($post);
                $this->fireEvent($repository, $post, $user, array('message' => $messageStruct->text));
                return;
            }
        }

        throw new UnauthorizedException("Current user can not edit this comment");
    }
}

