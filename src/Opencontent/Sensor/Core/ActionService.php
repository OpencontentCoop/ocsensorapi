<?php

namespace OpenContent\Sensor\Core;

use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\ActionService as ActionServiceInterface;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Exception\BaseException;

class ActionService implements ActionServiceInterface
{

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var ActionDefinition[]
     */
    protected $actionDefinitions = array();

    /**
     * @var Post
     */
    protected $post;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param Repository $repository
     * @param ActionDefinition[] $actionDefinitions
     */
    public function __construct( Repository $repository, $actionDefinitions )
    {
        $this->repository = $repository;
        foreach( $actionDefinitions as $actionDefinition )
        {
            $this->actionDefinitions[$actionDefinition->identifier] = $actionDefinition;
        }
        $this->setUser( $this->repository->getCurrentUser() );
    }

    public function loadActionDefinitionByIdentifier( $identifier )
    {
        if (isset($this->actionDefinitions[$identifier]))
            return $this->actionDefinitions[$identifier];
        throw new BaseException( "Action $identifier not defined" );
    }

    /**
     * @param Action $action
     * @param Post $post
     *
     * @return Action
     * @throws BaseException
     */
    public function dryRunAction( Action $action, Post $post )
    {
        return $this->loadActionDefinitionByIdentifier( $action->identifier )->dryRun(
            $this->repository,
            $action,
            $post,
            $this->user
        );
    }

    /**
     * @param Action $action
     * @param Post $post
     *
     * @throws BaseException
     */
    public function runAction( Action $action, Post $post )
    {
        $this->loadActionDefinitionByIdentifier( $action->identifier )->run(
            $this->repository,
            $this->dryRunAction( $action, $post ),
            $post,
            $this->user
        );
    }

    public function setUser( User $user )
    {
        $this->user = $user;
        return $this;
    }
}