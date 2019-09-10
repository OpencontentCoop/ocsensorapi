<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use SensorNotificationTextHelper;

class OnAddObserverNotificationType extends NotificationType
{
    public function __construct()
    {
        $this->identifier = 'on_add_observer';
        $this->name = ezpI18n::tr('sensor/notification', 'Coinvolgimento di un osservatore');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando un osservatore viene coinvolto in una segnalazione');
        $notificationTexts = SensorNotificationTextHelper::getTemplates();
        if (isset($notificationTexts['on_add_observer'])){
            $this->template = $notificationTexts['on_add_observer'];
        }
    }
}