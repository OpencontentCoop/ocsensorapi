<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class SetType extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'set_type';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_set_type');
        $this->inputName = 'SetType';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'type';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $type = $repository->getPostTypeService()->loadPostType($action->getParameterValue('type'));
        if ($post->type->identifier != $type->identifier) {
            $repository->getPostService()->setPostType($post, $type);

            $auditStruct = new AuditStruct();
            $auditStruct->createdDateTime = new \DateTime();
            $auditStruct->creator = $user;
            $auditStruct->post = $post;
            $auditStruct->text = "Impostata tipologia a $type->identifier";
            $repository->getMessageService()->createAudit($auditStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('type' => $type));
        }
    }
}