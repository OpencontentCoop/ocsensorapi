<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\Repository as RepositoryInterface;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\Settings;

abstract class Repository implements RepositoryInterface
{
    protected function __construct(){}

    /**
     * @var User
     */
    protected $user;

    protected $language;

    /**
     * @var PostService
     */
    protected $postService;

    /**
     * @var MessageService
     */
    protected $messageService;

    /**
     * @var SearchService
     */
    protected $searchService;

    /**
     * @var ParticipantService
     */
    protected $participantService;

    /**
     * @var PermissionService
     */
    protected $permissionService;

    /**
     * @var ActionService
     */
    protected $actionService;

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var EventService
     */
    protected $eventService;

    /**
     * @var PermissionDefinition[]
     */
    protected $permissionDefinitions = array();

    /**
     * @var ActionDefinition[]
     */
    protected $actionDefinitions = array();

    public function getCurrentUser()
    {
        return $this->user;
    }

    public function setCurrentUser( User $user )
    {
        $this->user = $user;
    }

    public function getCurrentLanguage()
    {
        return $this->language;
    }

    public function setCurrentLanguage( $language )
    {
        $this->language = $language;
    }

    public function isUserParticipant( Post $post )
    {
        return $post->participants->getUserById( $this->user->id );
    }

    public function getPermissionService()
    {
        if ( $this->permissionService === null )
        {
            $this->permissionService = new PermissionService( $this, $this->permissionDefinitions );
        }
        return $this->permissionService;
    }

    public function getActionService()
    {
        if ( $this->actionService === null )
        {
            $this->actionService = new ActionService( $this, $this->actionDefinitions );
        }
        return $this->actionService;
    }

    /**
     * @param ActionDefinition[] $actionDefinitions
     *
     * @return void
     */
    public function setActionDefinitions( $actionDefinitions )
    {
        $this->actionDefinitions = $actionDefinitions;
        $this->actionService = null;
    }

    /**
     * @param PermissionDefinition[] $permissionDefinitions
     *
     * @return void
     */
    public function setPermissionDefinitions( $permissionDefinitions )
    {
        $this->permissionDefinitions = $permissionDefinitions;
        $this->participantService = null;
    }
}
