<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnReopenNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_reopen';
        $this->name = ezpI18n::tr('sensor/notification', 'Riapertura di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica alla riapertura di una tua segnalazione");
    }
}