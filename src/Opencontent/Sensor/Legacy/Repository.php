<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Core\Repository as CoreRepository;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Legacy\PostService;
use OpenContent\Sensor\Legacy\MessageService;
use OpenContent\Sensor\Legacy\ParticipantService;
use OpenContent\Sensor\Legacy\EventService;
use OpenContent\Sensor\Api\SearchService;
use OpenContent\Sensor\Utils\TreeNode;
use OpenContent\Sensor\Utils\TreeNodeItem;
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
     * @return \OpenContent\Sensor\Legacy\CachePostService
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
     * @return \OpenContent\Sensor\Legacy\MessageService
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
        // TODO: Implement getSearchService() method.
    }

    /**
     * @return \OpenContent\Sensor\Legacy\ParticipantService
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
     * @return \OpenContent\Sensor\Legacy\CachedUserService
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