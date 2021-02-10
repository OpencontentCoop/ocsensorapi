<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class RemoveAttachmentAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'remove_attachment';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_remove_attachment');
        $this->inputName = 'RemoveAttachment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'files';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $files = (array)$action->getParameterValue('files');

        $repository->getPostService()->removeAttachment($post, $files);

        $auditStruct = new AuditStruct();
        $auditStruct->createdDateTime = new \DateTime();
        $auditStruct->creator = $user;
        $auditStruct->post = $post;
        $auditStruct->text = "Rimosso allegato " . implode(', ', $files);
        $repository->getMessageService()->createAudit($auditStruct);

        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, array('files' => $files));
    }
}
