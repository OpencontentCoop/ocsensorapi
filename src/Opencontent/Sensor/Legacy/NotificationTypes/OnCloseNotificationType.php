<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnCloseNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_close';
        $this->name = ezpI18n::tr('sensor/notification', 'Chiusura di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica quando una tua segnalazione è stata chiusa");
    }
}