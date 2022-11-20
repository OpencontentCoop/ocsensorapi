<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\NotificationService as CoreNotificationService;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\User;

class NotificationService extends CoreNotificationService
{

    /**
     * @inheritDoc
     */
    public function getNotificationTypes()
    {
        // TODO: Implement getNotificationTypes() method.
    }

    /**
     * @inheritDoc
     */
    public function setNotificationTypes($notificationTypes)
    {
        // TODO: Implement setNotificationTypes() method.
    }

    /**
     * @inheritDoc
     */
    public function addNotificationType($notificationType)
    {
        // TODO: Implement addNotificationType() method.
    }

    /**
     * @inheritDoc
     */
    public function getNotificationByIdentifier($notificationIdentifier)
    {
        // TODO: Implement getNotificationByIdentifier() method.
    }

    public function addUserToNotification(User $user, NotificationType $notification)
    {
        // TODO: Implement addUserToNotification() method.
    }

    public function removeUserToNotification(User $user, NotificationType $notification)
    {
        // TODO: Implement removeUserToNotification() method.
    }

    public function getUserNotifications(User $user)
    {
        // TODO: Implement getUserNotifications() method.
    }

    public function getUsersByNotification(NotificationType $notification)
    {
        // TODO: Implement getUsersByNotification() method.
    }
}