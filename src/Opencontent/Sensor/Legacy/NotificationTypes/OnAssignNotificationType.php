<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use SensorNotificationTextHelper;

class OnAssignNotificationType extends NotificationType
{
    public function __construct()
    {
        $this->identifier = 'on_assign';
        $this->name = ezpI18n::tr('sensor/notification', 'Assegnazione di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando una tua segnalazione è assegnata a un responsabile');
        $notificationTexts = SensorNotificationTextHelper::getTemplates();
        if (isset($notificationTexts['on_assign'])){
            $this->template = $notificationTexts['on_assign'];
        }
    }
}