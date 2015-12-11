<?php

namespace OpenContent\Sensor\Api;

use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Exception\PermissionException;
use OpenContent\Sensor\Api\Exception\InvalidParameterException;

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
