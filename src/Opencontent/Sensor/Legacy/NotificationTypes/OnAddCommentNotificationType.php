<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use SensorNotificationTextHelper;

class OnAddCommentNotificationType extends NotificationType
{
    public function __construct()
    {
        $this->identifier = 'on_add_comment';
        $this->name = ezpI18n::tr('sensor/notification', 'Commento a una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando viene aggiunto un commento ad una tua segnalazione');
        $notificationTexts = SensorNotificationTextHelper::getTemplates();
        if (isset($notificationTexts['on_add_comment'])){
            $this->template = $notificationTexts['on_add_comment'];
        }
    }
}