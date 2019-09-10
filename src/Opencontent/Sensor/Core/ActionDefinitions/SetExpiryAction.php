<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class SetExpiryAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'set_expiry';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_set_expiry');
        $this->inputName = 'SetExpiry';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'expiry_days';
        $parameter->isRequired = true;
        $parameter->type = 'int';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $expiryDays = intval($action->getParameterValue('expiry_days'));

        if ($expiryDays > 0) {
            $repository->getPostService()->setPostExpirationInfo($post, $expiryDays);
            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('expiry' => $expiryDays));
        } else {
            throw new InvalidInputException("Invalid days input");
        }
    }
}
