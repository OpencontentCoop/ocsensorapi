<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\OperatorService;
use Opencontent\Sensor\Api\Repository as RepositoryInterface;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\Settings;

abstract class Repository implements RepositoryInterface
{
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
     * @var \Opencontent\Sensor\Api\AreaService
     */
    protected $areaService;

    /**
     * @var \Opencontent\Sensor\Api\CategoryService
     */
    protected $categoryService;

    /**
     * @var \Opencontent\Sensor\Api\OperatorService
     */
    protected $operatorService;

    /**
     * @var \Opencontent\Sensor\Api\GroupService
     */
    protected $groupService;

    /**
     * @var \Opencontent\Sensor\Api\StatisticsService
     */
    protected $statisticsService;

    /**
     * @var PermissionDefinition[]
     */
    protected $permissionDefinitions = array();

    /**
     * @var ActionDefinition[]
     */
    protected $actionDefinitions = array();

    /**
     * @var \Opencontent\Sensor\Api\NotificationService
     */
    protected $notificationService;

    /**
     * @var \Opencontent\Sensor\Api\PostTypeService
     */
    protected $typeService;

    /**
     * @var \Opencontent\Sensor\Api\ChannelService
     */
    protected $channelService;

    /**
     * @var \Opencontent\Sensor\Api\ScenarioService
     */
    protected $scenarioService;

    /**
     * @var \Opencontent\Sensor\Api\FaqService
     */
    protected $faqService;

    public function getCurrentUser()
    {
        return $this->user;
    }

    public function setCurrentUser(User $user)
    {
        $this->user = $user;
    }

    public function getCurrentLanguage()
    {
        return $this->language;
    }

    public function setCurrentLanguage($language)
    {
        $this->language = $language;
    }

    public function isUserParticipant(Post $post)
    {
        return $post->participants->getUserById($this->user->id);
    }

    public function getPermissionService()
    {
        if ($this->permissionService === null) {
            $this->permissionService = new PermissionService($this, $this->permissionDefinitions);
        }
        return $this->permissionService;
    }

    public function getActionService()
    {
        if ($this->actionService === null) {
            $this->actionService = new ActionService($this, $this->actionDefinitions);
        }
        return $this->actionService;
    }

    /**
     * @param ActionDefinition[] $actionDefinitions
     *
     * @return void
     */
    public function setActionDefinitions($actionDefinitions)
    {
        $this->actionDefinitions = $actionDefinitions;
        $this->actionService = null;
    }

    /**
     * @param PermissionDefinition[] $permissionDefinitions
     *
     * @return void
     */
    public function setPermissionDefinitions($permissionDefinitions)
    {
        $this->permissionDefinitions = $permissionDefinitions;
        $this->permissionService = null;
        $this->participantService = null;        
    }
}
