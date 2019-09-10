<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use SensorNotificationTextHelper;

class OnReopenNotificationType extends NotificationType
{
    public function __construct()
    {
        $this->identifier = 'on_reopen';
        $this->name = ezpI18n::tr('sensor/notification', 'Riapertura di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica alla riapertura di una tua segnalazione");
        $notificationTexts = SensorNotificationTextHelper::getTemplates();
        if (isset($notificationTexts['on_reopen'])){
            $this->template = $notificationTexts['on_reopen'];
        }
    }
}