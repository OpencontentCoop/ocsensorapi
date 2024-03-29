<?php

namespace Opencontent\Sensor\Api\Action;

use Opencontent\Sensor\Api\Exception\RequiredParameterException;
use Opencontent\Sensor\Api\Exception\PermissionException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\Event;

abstract class ActionDefinition
{
    public $identifier;

    public $inputName;

    /**
     * @var ActionDefinitionParameter[]
     */
    public $parameterDefinitions = array();

    /**
     * @var string[]
     */
    public $permissionDefinitionIdentifiers = array();

    /**
     * @param Repository $repository
     * @param \Opencontent\Sensor\Api\Action\Action $action
     * @param Post $post
     * @param User $user
     *
     * @return Action
     *
     * @throws RequiredParameterException
     * @throws PermissionException
     */
    public function dryRun(Repository $repository, Action $action, Post $post, User $user)
    {
        if (!$action->isIgnorePermissionEnabled()) {
            $this->checkPermission($post, $user);
        }
        return $this->checkParameters($action);
    }

    /**
     * @param Repository $repository
     * @param Action $action
     * @param Post $post
     * @param User $user
     *
     * @return mixed
     */
    abstract public function run(Repository $repository, Action $action, Post $post, User $user);

    protected function checkPermission(Post $post, User $user)
    {
        foreach ($this->permissionDefinitionIdentifiers as $permissionDefinitionIdentifier) {
            if (!$user->permissions->hasPermission($permissionDefinitionIdentifier))
                throw new PermissionException($permissionDefinitionIdentifier, $user, $post);
        }
    }

    protected function checkParameters(Action $action)
    {
        foreach ($this->parameterDefinitions as $parameterDefinition) {
            if (!$action->hasParameter($parameterDefinition->identifier)) {
                if ($parameterDefinition->isRequired)
                    throw new RequiredParameterException($parameterDefinition);
                else
                    $action->setParameter($parameterDefinition->identifier, $parameterDefinition->defaultValue);
            }

        }
        return $action;
    }

    protected function fireEvent(Repository $repository, Post $post, User $user, $eventParameters = array(), $eventIdentifier = null)
    {
        $event = new Event();
        $event->identifier = $eventIdentifier ? $eventIdentifier : 'on_' . $this->identifier;
        $event->post = $post;
        $event->user = $user;
        $event->parameters = $eventParameters;
        $repository->getEventService()->fire($event);
    }

    protected function arrayIsEqual($a, $b)
    {
        return count($a) == count($b) && array_diff($a, $b) === array_diff($b, $a);
    }
}