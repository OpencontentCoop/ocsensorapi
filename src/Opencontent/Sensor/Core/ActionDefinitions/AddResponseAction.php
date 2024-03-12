<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\ResponseStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddResponseAction extends ActionDefinition
{

    public function __construct()
    {
        $this->identifier = 'add_response';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_respond');
        $this->inputName = 'Respond';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
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
        if (trim($text) == ''){
            throw new InvalidInputException("Text is required");
        }

        $responseStruct = new ResponseStruct();
        $responseStruct->createdDateTime = new \DateTime();
        $responseStruct->creator = $repository->getCurrentUser();
        $responseStruct->post = $post;
        $responseStruct->text = $text;
        $responseStruct->externalId = $action->getParameterValue('external_id');

        $repository->getMessageService()->createResponse($responseStruct);
        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, array('text' => $text));
    }
}
