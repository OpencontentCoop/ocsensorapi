<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Exception\PermissionException;
use Opencontent\Sensor\Api\Exception\InvalidParameterException;

interface ActionService
{
    /**
     * @param User $user
     *
     * @return ActionService
     */
    public function setUser( User $user );

    /**
     * @param $identifier
     *
     * @return ActionDefinition
     */
    public function loadActionDefinitionByIdentifier( $identifier );

    /**
     * @param Action $action
     *
     * @return Action
     * @param Post $post
     *
     * @throws PermissionException
     * @throws InvalidParameterException
     */
    public function dryRunAction( Action $action, Post $post );

    /**
     * @param Action $action
     * @param Post $post
     *
     * @throws PermissionException
     * @throws InvalidParameterException
     */
    public function runAction( Action $action, Post $post );

}
