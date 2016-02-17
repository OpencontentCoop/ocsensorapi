<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Core\Repository as CoreRepository;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Legacy\PostService;
use Opencontent\Sensor\Legacy\MessageService;
use Opencontent\Sensor\Legacy\ParticipantService;
use Opencontent\Sensor\Legacy\EventService;
use Opencontent\Sensor\Legacy\SearchService;
use Opencontent\Sensor\Legacy\Utils\TreeNode;
use Opencontent\Sensor\Legacy\Utils\TreeNodeItem;
use eZContentObjectTreeNode;
use eZContentClass;
use eZContentObjectState;
use ArrayAccess;

abstract class Repository extends CoreRepository
{
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
     * @param $identifier
     *
     * @return eZContentObjectState[]
     */
    abstract public function getSensorPostStates( $identifier );

    /**
     * @return TreeNodeItem
     */
    public function getAreasTree()
    {
        return TreeNode::walk( $this->getAreasRootNode(), array( 'classes' => array( 'sensor_area' )) );
    }

    /**
     * @return TreeNodeItem
     */
    public function getCategoriesTree()
    {
        return TreeNode::walk( $this->getCategoriesRootNode() );
    }

    /**
     * @return \Opencontent\Sensor\Legacy\CachePostService
     */
    public function getPostService()
    {
        if ( $this->postService === null )
        {
            $this->postService = new CachePostService( $this, $this->permissionDefinitions );
        }
        return $this->postService;
    }

    /**
     * @return \Opencontent\Sensor\Legacy\MessageService
     */
    public function getMessageService()
    {
        if ( $this->messageService === null )
        {
            $this->messageService = new MessageService( $this, $this->permissionDefinitions );
        }
        return $this->messageService;
    }

    public function getSearchService()
    {
        if ( $this->searchService === null )
        {
            $this->searchService = new SearchService( $this );
        }
        return $this->searchService;
    }

    /**
     * @return \Opencontent\Sensor\Legacy\ParticipantService
     */
    public function getParticipantService()
    {
        if ( $this->participantService === null )
        {
            $this->participantService = new ParticipantService( $this, $this->permissionDefinitions );
        }
        return $this->participantService;
    }

    /**
     * @return \Opencontent\Sensor\Legacy\CachedUserService
     */
    public function getUserService()
    {
        if ( $this->userService === null )
        {
            $this->userService = new CachedUserService( $this, $this->permissionDefinitions );
        }
        return $this->userService;
    }

    public function getEventService()
    {
        if ( $this->eventService === null )
        {
            $this->eventService = new EventService( $this );
        }
        return $this->eventService;
    }

}