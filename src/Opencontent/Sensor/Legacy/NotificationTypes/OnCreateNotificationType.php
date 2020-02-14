<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnCreateNotificationType extends NotificationType
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_create';
        $this->name = ezpI18n::tr('sensor/notification', 'Creazione di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica alla creazione di una segnalazione');
        $this->setTemplate();
    }
}