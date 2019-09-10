<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use SensorNotificationTextHelper;

class OnCloseNotificationType extends NotificationType
{
    public function __construct()
    {
        $this->identifier = 'on_close';
        $this->name = ezpI18n::tr('sensor/notification', 'Chiusura di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica quando una tua segnalazione è stata chiusa");
        $notificationTexts = SensorNotificationTextHelper::getTemplates();
        if (isset($notificationTexts['on_close'])){
            $this->template = $notificationTexts['on_close'];
        }
    }
}