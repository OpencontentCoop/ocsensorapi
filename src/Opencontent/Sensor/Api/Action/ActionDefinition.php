<?php

namespace OpenContent\Sensor\Api\Action;

use OpenContent\Sensor\Api\Exception\InvalidParameterException;
use OpenContent\Sensor\Api\Exception\PermissionException;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\Event;

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
     * @param \OpenContent\Sensor\Api\Action\Action $action
     * @param Post $post
     * @param User $user
     *
     * @return Action
     *
     * @throws InvalidParameterException
     * @throws PermissionException
     */
    public function dryRun( Repository $repository, Action $action, Post $post, User $user )
    {
        $this->checkPermission( $post, $user );
        return $this->checkParameters( $action );
    }

    /**
     * @param Repository $repository
     * @param Action $action
     * @param Post $post
     * @param User $user
     *
     * @return mixed
     */
    abstract public function run( Repository $repository, Action $action, Post $post, User $user );

    protected function checkPermission( Post $post, User $user )
    {
        foreach( $this->permissionDefinitionIdentifiers as $permissionDefinitionIdentifier )
        {
            if ( !$user->permissions->hasPermission( $permissionDefinitionIdentifier ) )
                throw new PermissionException( $permissionDefinitionIdentifier, $user, $post );
        }
    }

    protected function checkParameters( Action $action )
    {
        foreach( $this->parameterDefinitions as $parameterDefinition )
        {
            if ( !$action->hasParameter( $parameterDefinition->identifier ) )
            {
                if ( $parameterDefinition->isRequired )
                    throw new InvalidParameterException( $parameterDefinition );
                else
                    $action->setParameter( $parameterDefinition->identifier, $parameterDefinition->defaultValue );
            }

        }
        return $action;
    }

    protected function fireEvent( Repository $repository, Post $post, User $user, $eventParameters = array() )
    {
        $event = new Event();
        $event->identifier = 'on_' . $this->identifier;
        $event->post = $post;
        $event->user = $user;
        $event->parameters = $eventParameters;
        $repository->getEventService()->fire( $event );
    }
}