<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddAttachmentAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_attachment';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_add_attachment');
        $this->inputName = 'AddAttachment';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'files';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $files = $action->getParameterValue('files');
        if (isset($files['filename'])) { //@todo correggere lo schema
            $files = [$files];
        }
        if (count($files) > 0) {
            $repository->getPostService()->addAttachment($post, $files);

            $auditStruct = new AuditStruct();
            $auditStruct->createdDateTime = new \DateTime();
            $auditStruct->creator = $user;
            $auditStruct->post = $post;
            $auditStruct->text = "Aggiunto allegato " . implode(', ', array_column($files, 'filename'));
            $repository->getMessageService()->createAudit($auditStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('files' => array_column($files, 'filename')));
        }
    }
}
