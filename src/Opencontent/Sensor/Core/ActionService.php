<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\ActionService as ActionServiceInterface;
use Opencontent\Sensor\Api\Values\Event;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Exception\BaseException;

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

    protected $currentAction;

    /**
     * @param Repository $repository
     * @param ActionDefinition[] $actionDefinitions
     */
    public function __construct(Repository $repository, $actionDefinitions)
    {
        $this->repository = $repository;
        foreach ($actionDefinitions as $actionDefinition) {
            $this->actionDefinitions[$actionDefinition->identifier] = $actionDefinition;
        }
        $this->setUser($this->repository->getCurrentUser());
    }

    public function loadActionDefinitionByIdentifier($identifier)
    {
        if (isset($this->actionDefinitions[$identifier]))
            return $this->actionDefinitions[$identifier];
        throw new BaseException("Action $identifier not defined");
    }

    /**
     * @param Action $action
     * @param Post $post
     *
     * @return Action
     * @throws BaseException
     */
    public function dryRunAction(Action $action, Post $post)
    {
        return $this->loadActionDefinitionByIdentifier($action->identifier)->dryRun(
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
    public function runAction(Action $action, Post $post)
    {
        if ($this->currentAction === null){
            $this->currentAction = $action->identifier;
            $event = new Event();
            $event->identifier = 'before_run_action';
            $event->post = $post;
            $event->user = $this->repository->getCurrentUser();
            $event->parameters = [
                'action' => $action->identifier,
                'is_main' => true,
            ];
            $this->repository->getEventService()->fire($event);
        }else{
            $event = new Event();
            $event->identifier = 'before_run_action';
            $event->post = $post;
            $event->user = $this->repository->getCurrentUser();
            $event->parameters = [
                'action' => $action->identifier,
                'is_main' => false,
            ];
            $this->repository->getEventService()->fire($event);
        }

        $this->loadActionDefinitionByIdentifier($action->identifier)->run(
            $this->repository,
            $this->dryRunAction($action, $post),
            $post,
            $this->user
        );

        if ($action->identifier == $this->currentAction){
            $this->currentAction = null;
            $event = new Event();
            $event->identifier = 'after_run_action';
            $event->post = $post;
            $event->user = $this->repository->getCurrentUser();
            $event->parameters = [
                'action' => $action->identifier,
                'is_main' => true,
            ];
            $this->repository->getEventService()->fire($event);
            $this->repository->getPostService()->doRefreshPost($post);
        }else{
            $event = new Event();
            $event->identifier = 'after_run_action';
            $event->post = $post;
            $event->user = $this->repository->getCurrentUser();
            $event->parameters = [
                'action' => $action->identifier,
                'is_main' => false,
            ];
            $this->repository->getEventService()->fire($event);
        }
    }

    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }
}