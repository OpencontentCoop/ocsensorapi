<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class ModerateAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'moderate';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_moderate');
        $this->inputName = 'Moderate';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'status';
        $parameter->type = 'string';
        $parameter->defaultValue = 'accepted';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $identifier = $action->getParameterValue('status');
        $repository->getPostService()->setPostStatus($post, 'moderation.' . $identifier);
        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user);
    }
}