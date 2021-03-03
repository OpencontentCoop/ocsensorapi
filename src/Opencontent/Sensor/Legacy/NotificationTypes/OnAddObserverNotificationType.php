<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnAddObserverNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_observer';
        $this->name = ezpI18n::tr('sensor/notification', 'Coinvolgimento di un osservatore');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando un osservatore viene coinvolto in una segnalazione');
    }
}