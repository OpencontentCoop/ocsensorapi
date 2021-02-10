<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\UnauthorizedException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Message\ResponseStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class EditResponseAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'edit_response';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_respond');
        $this->inputName = 'EditResponse';

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
        $responseStruct = new ResponseStruct();
        $responseStruct->id = $action->getParameterValue('id');
        $responseStruct->text = $action->getParameterValue('text');
        $responseStruct->creator = $user;

        /** @var Message $response */
        foreach ($post->responses as $response) {
            if ($response->id == $responseStruct->id && $response->creator->id == $user->id) {
                $oldText = $response->text;
                $repository->getMessageService()->updateResponse($responseStruct);

                $auditStruct = new AuditStruct();
                $auditStruct->createdDateTime = new \DateTime();
                $auditStruct->creator = $user;
                $auditStruct->post = $post;
                $auditStruct->text = "Modificata risposta #{$response->id}, il testo precedente era: {$oldText}";
                $repository->getMessageService()->createAudit($auditStruct);

                $post = $repository->getPostService()->refreshPost($post);
                $this->fireEvent($repository, $post, $user, array('message' => $responseStruct->text));
                return;
            }
        }

        throw new UnauthorizedException("Current user can not edit this response");
    }
}