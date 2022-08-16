<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class SetProtocolAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'set_protocol';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_set_protocol');
        $this->inputName = 'SetProtocol';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'protocol1';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'protocol2';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'protocol3';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $protocol1 = $action->getParameterValue('protocol1');
        $protocol2 = $action->getParameterValue('protocol2');
        $protocol3 = $action->getParameterValue('protocol3');

        $protocols = [$protocol1, $protocol2, $protocol3,];
        $repository->getPostService()->setPostProtocols($post, $protocols);

        $auditStruct = new AuditStruct();
        $auditStruct->createdDateTime = new \DateTime();
        $auditStruct->creator = $user;
        $auditStruct->post = $post;
        $auditStruct->text = "Impostati protocolli: " . implode(', ', $protocols);
        $repository->getMessageService()->createAudit($auditStruct);

        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, array('protocols' => $protocols));
    }
}
