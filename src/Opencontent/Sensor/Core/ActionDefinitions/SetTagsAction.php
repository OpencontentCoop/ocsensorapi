<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class SetTagsAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'set_tags';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_set_tags');
        $this->inputName = 'SetTags';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'tags';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $tags = (array)$action->getParameterValue('tags');
        $repository->getPostService()->setPostTags($post, $tags);

        $auditStruct = new AuditStruct();
        $auditStruct->createdDateTime = new \DateTime();
        $auditStruct->creator = $user;
        $auditStruct->post = $post;
        if (empty($tags)){
            $auditStruct->text = "Rimossi tag";
        }else{
            $auditStruct->text = "Impostati tag: " . implode(', ', $tags);
        }
        $repository->getMessageService()->createAudit($auditStruct);

        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, array('tags' => $tags));
    }

}
