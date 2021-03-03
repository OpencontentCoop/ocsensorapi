<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnFixNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_fix';
        $this->name = ezpI18n::tr('sensor/notification', 'Intervento terminato');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica quando un responsabile ha completato l'attività che riguarda una tua segnalazione");
    }
}