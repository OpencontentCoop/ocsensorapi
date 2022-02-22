<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\User;
use ezpI18n;
use eZCollaborationNotificationRule;

class NotificationService extends \Opencontent\Sensor\Core\NotificationService
{
    /**
     * @var \OpenPaSensorRepository
     */
    protected $repository;

    private $notificationTypes = [];

    /**
     * @return NotificationType[]
     */
    public function getNotificationTypes()
    {
        return $this->notificationTypes;
    }

    public function setNotificationTypes($notificationTypes)
    {
        $this->notificationTypes = $notificationTypes;
    }

    public function addNotificationType($notificationType)
    {
        $this->notificationTypes[] = $notificationType;
    }

    public function getNotificationByIdentifier($notificationIdentifier)
    {
        foreach ($this->getNotificationTypes() as $notificationType) {
            if ($notificationType->identifier == $notificationIdentifier) {
                if ($notificationType instanceof NotificationTypes\TemplateAwareNotificationTypeInterface){
                    $notificationType->initTemplate();
                }
                return $notificationType;
            }
        }

        return null;
    }

    public function addUserToNotification(User $user, NotificationType $notification)
    {
        $notificationPrefix = $this->repository->getSensorCollaborationHandlerTypeString() . '_';
        $notificationRules = [];
        $notificationRules[] = $notificationPrefix . $notification->identifier;
        $db = \eZDB::instance();
        $db->begin();
        foreach ($notificationRules as $rule) {
            eZCollaborationNotificationRule::create($rule, $user->id)->store();
        }

        $db->commit();
    }

    public function removeUserToNotification(User $user, NotificationType $notification)
    {
        $notificationPrefix = $this->repository->getSensorCollaborationHandlerTypeString() . '_';
        $notificationRules = [];
        $notificationRules[] = $notificationPrefix . $notification->identifier;

        $db = \eZDB::instance();
        $db->begin();
        foreach ($notificationRules as $rule) {
            /** @var eZCollaborationNotificationRule[] $subscriptions */
            $subscriptions = (array)\eZPersistentObject::fetchObjectList(
                eZCollaborationNotificationRule::definition(),
                null,
                array('user_id' => $user->id, 'collab_identifier' => array($notificationRules))
            );
            foreach ($subscriptions as $subscription) {
                $subscription->remove();
            }
        }

        $db->commit();
    }

    public function getUserNotifications(User $user)
    {
        $notificationPrefix = $this->repository->getSensorCollaborationHandlerTypeString() . '_';
        $notificationTypes = $this->getNotificationTypes();
        $searchNotificationRules = array();
        foreach ($notificationTypes as $type) {
            $searchNotificationRules[] = $notificationPrefix . $type->identifier;
        }
        /** @var eZCollaborationNotificationRule[] $subscriptions */
        $subscriptions = (array)\eZPersistentObject::fetchObjectList(
            eZCollaborationNotificationRule::definition(),
            null,
            array('user_id' => $user->id, 'collab_identifier' => array($searchNotificationRules))
        );

        $result = array();
        foreach ($subscriptions as $subscription) {
            $collaborationIdentifier = $subscription->attribute('collab_identifier');
            $identifier = str_replace($notificationPrefix, '', $collaborationIdentifier);
            $result[] = $identifier;

        }

        return $result;
    }

    public function getUsersByNotification(NotificationType $notification)
    {
        $notificationPrefix = $this->repository->getSensorCollaborationHandlerTypeString() . '_';
        $searchNotificationRules = [$notificationPrefix . $notification->identifier];

        /** @var eZCollaborationNotificationRule[] $subscriptions */
        $subscriptions = (array)\eZPersistentObject::fetchObjectList(
            eZCollaborationNotificationRule::definition(),
            null,
            array('collab_identifier' => array($searchNotificationRules))
        );

        $result = array();
        foreach ($subscriptions as $subscription) {
            $result[] = $subscription->attribute('user_id');

        }

        return $result;
    }

}
