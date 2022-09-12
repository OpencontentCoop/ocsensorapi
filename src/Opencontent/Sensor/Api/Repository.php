<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\ChannelService;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\PostService;
use Opencontent\Sensor\Api\MessageService;
use Opencontent\Sensor\Api\SearchService;
use Opencontent\Sensor\Api\ParticipantService;
use Opencontent\Sensor\Api\PermissionService;
use Opencontent\Sensor\Api\ActionService;
use Opencontent\Sensor\Api\UserService;
use Opencontent\Sensor\Api\EventService;
use Opencontent\Sensor\Api\ScenarioService;
use Opencontent\Sensor\Api\Values\Settings;
use Opencontent\Sensor\Api\SubscriptionService;
use Psr\Log\LoggerInterface;

interface Repository
{

    public static function instance();

    /**
     * @return User
     */
    public function getCurrentUser();

    public function setCurrentUser(User $user);

    public function getCurrentLanguage();

    public function setCurrentLanguage($language);

    public function isUserParticipant(Post $post);

    /**
     * @return Settings
     */
    public function getSensorSettings();

    /**
     * @return PostService
     */
    public function getPostService();

    /**
     * @return MessageService
     */
    public function getMessageService();

    /**
     * @return SearchService
     */
    public function getSearchService();

    /**
     * @return ParticipantService
     */
    public function getParticipantService();

    /**
     * @return PermissionService
     */
    public function getPermissionService();

    /**
     * @return ActionService
     */
    public function getActionService();

    /**
     * @return UserService
     */
    public function getUserService();

    /**
     * @return EventService
     */
    public function getEventService();

    /**
     * @return AreaService
     */
    public function getAreaService();

    /**
     * @return CategoryService
     */
    public function getCategoryService();

    /**
     * @return OperatorService
     */
    public function getOperatorService();

    /**
     * @return GroupService
     */
    public function getGroupService();

    /**
     * @return LoggerInterface
     */
    public function getLogger();

    /**
     * @return NotificationService
     */
    public function getNotificationService();

    /**
     * @return StatisticsService
     */
    public function getStatisticsService();

    /**
     * @return PostTypeService
     */
    public function getPostTypeService();

    /**
     * @return ChannelService
     */
    public function getChannelService();

    /**
     * @return ScenarioService
     */
    public function getScenarioService();

    /**
     * @return FaqService
     */
    public function getFaqService();

    /**
     * @return PostStatusService
     */
    public function getPostStatusService();

    /**
     * @return SubscriptionService
     */
    public function getSubscriptionService();
}