<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnGroupAssignNotificationType extends NotificationType
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_group_assign';
        $this->name = ezpI18n::tr('sensor/notification', 'Assegnazione di una segnalazione a un gruppo');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando una segnalazione viene assegnata al tuo gruppo');
        $this->setTemplate();
    }

}