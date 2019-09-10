<?php

namespace Opencontent\Sensor\Legacy;

use League\Event\ListenerAcceptorInterface;
use League\Event\ListenerInterface;
use League\Event\ListenerProviderInterface;
use Opencontent\Sensor\Core\Repository as CoreRepository;
use Opencontent\Sensor\Legacy\PostService\ScenarioInterface;
use Opencontent\Sensor\Legacy\Utils\Logger;
use Opencontent\Sensor\Legacy\Utils\TreeNode;
use Opencontent\Sensor\Legacy\Utils\TreeNodeItem;
use eZContentObjectTreeNode;
use eZContentClass;
use eZContentObjectState;

abstract class Repository extends CoreRepository implements ListenerProviderInterface
{
    protected $scenarios = [];

    /**
     * @var array
     */
    protected $listeners = array();

    protected $useCache = false;

    protected function __construct()
    {
        $this->addListener('*', new Listeners\LogListener($this));
    }

    /**
     * @return string
     */
    abstract public function getSensorCollaborationHandlerTypeString();

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getRootNode();

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getPostRootNode();

    /**
     * @return eZContentClass
     */
    abstract public function getPostContentClass();

    /**
     * @return string
     */
    abstract public function getPostContentClassIdentifier();

    /**
     * @return \eZContentClassAttribute
     */
    abstract public function getPostContentClassAttribute($identifier);

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getOperatorsRootNode();

    /**
     * @return eZContentClass
     */
    abstract public function getOperatorContentClass();

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getUserRootNode();

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getAreasRootNode();

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getCategoriesRootNode();

    /**
     * @return eZContentObjectTreeNode
     */
    abstract public function getGroupsRootNode();

    /**
     * @param $identifier
     *
     * @return eZContentObjectState[]
     */
    abstract public function getSensorPostStates($identifier);

    /**
     * @return boolean
     */
    abstract public function isModerationEnabled();

    /**
     * @param $identifier
     * @return \eZContentObjectAttribute|null
     */
    abstract public function getRootNodeAttribute($identifier);

    /**
     * @return TreeNodeItem
     */
    public function getAreasTree()
    {
        return TreeNode::walk($this->getAreasRootNode(), array('classes' => array('sensor_area')));
    }

    /**
     * @return TreeNodeItem
     */
    public function getCategoriesTree()
    {
        return TreeNode::walk($this->getCategoriesRootNode());
    }

    /**
     * @return \Opencontent\Sensor\Legacy\PostService
     */
    public function getPostService()
    {
        if ($this->postService === null) {
            $this->postService = $this->useCache ? new CachedPostService($this) : new PostService($this);
        }
        return $this->postService;
    }

    /**
     * @return \Opencontent\Sensor\Legacy\MessageService
     */
    public function getMessageService()
    {
        if ($this->messageService === null) {
            $this->messageService = new MessageService($this);
        }
        return $this->messageService;
    }

    public function getSearchService()
    {
        if ($this->searchService === null) {
            $this->searchService = new SearchService($this);
        }
        return $this->searchService;
    }

    /**
     * @return \Opencontent\Sensor\Legacy\ParticipantService
     */
    public function getParticipantService()
    {
        if ($this->participantService === null) {
            $this->participantService = new ParticipantService($this);
        }
        return $this->participantService;
    }

    /**
     * @return \Opencontent\Sensor\Legacy\UserService
     */
    public function getUserService()
    {
        if ($this->userService === null) {
            $this->userService = $this->useCache ? new CachedUserService($this) : new UserService($this);
        }
        return $this->userService;
    }

    public function getEventService()
    {
        if ($this->eventService === null) {
            $this->eventService = new EventService($this);
            $this->eventService->getEmitter()->useListenerProvider($this);
        }
        return $this->eventService;
    }

    public function getAreaService()
    {
        if ($this->areaService === null) {
            $this->areaService = new AreaService($this);
        }
        return $this->areaService;
    }

    public function getCategoryService()
    {
        if ($this->categoryService === null) {
            $this->categoryService = new CategoryService($this);
        }
        return $this->categoryService;
    }

    public function getOperatorService()
    {
        if ($this->operatorService === null) {
            $this->operatorService = new OperatorService($this);
        }
        return $this->operatorService;
    }

    public function getGroupService()
    {
        if ($this->groupService === null) {
            $this->groupService = new GroupService($this);
        }
        return $this->groupService;
    }

    public function getNotificationService()
    {
        if ($this->notificationService === null) {
            $this->notificationService = new NotificationService($this);
        }
        return $this->notificationService;
    }

    public function getStatisticsService()
    {
        if ($this->statisticsService === null) {
            $this->statisticsService = new StatisticsService($this);
        }
        return $this->statisticsService;
    }

    public function getLogger()
    {
        return new Logger();
    }

    /**
     * @return ScenarioInterface[]
     */
    public function getScenarios()
    {
        return $this->scenarios;
    }

    /**
     * @param ScenarioInterface[] $scenarios
     */
    public function setScenarios($scenarios)
    {
        $this->scenarios = $scenarios;
    }

    public function provideListeners(ListenerAcceptorInterface $listenerAcceptor)
    {
        foreach ($this->listeners as $event => $priorityListeners){
            foreach ($priorityListeners as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $listenerAcceptor->addListener($event, $listener, $priority);
                }
            }
        }
    }

    public function addListener($event, ListenerInterface $listener, $priority = ListenerAcceptorInterface::P_NORMAL)
    {
        $this->listeners[$event][$priority][] = $listener;
        $this->eventService = null;
    }
}