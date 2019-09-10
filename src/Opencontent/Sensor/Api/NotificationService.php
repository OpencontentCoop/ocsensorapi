<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\User;

interface NotificationService
{
    /**
     * @return NotificationType[]
     */
    public function getNotificationTypes();

    /**
     * @param NotificationType[] $notificationTypes
     * @return void
     */
    public function setNotificationTypes($notificationTypes);

    /**
     * @param NotificationType $notificationType
     * @return void
     */
    public function addNotificationType($notificationType);

    /**
     * @param $notificationIdentifier
     * @return NotificationType
     */
    public function getNotificationByIdentifier($notificationIdentifier);

    public function addUserToNotification(User $user, NotificationType $notification);

    public function removeUserToNotification(User $user, NotificationType $notification);

    public function getUserNotifications(User $user);
}