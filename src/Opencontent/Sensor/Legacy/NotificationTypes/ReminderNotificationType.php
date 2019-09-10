<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use SensorNotificationTextHelper;

class ReminderNotificationType extends NotificationType
{
    public function __construct()
    {
        $this->identifier = 'reminder';
        $this->name = ezpI18n::tr('sensor/notification', 'Notifica di aggiornamento');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica periodica di aggiornamento");
        $notificationTexts = SensorNotificationTextHelper::getTemplates();
        if (isset($notificationTexts['reminder'])){
            $this->template = $notificationTexts['reminder'];
        }
    }
}