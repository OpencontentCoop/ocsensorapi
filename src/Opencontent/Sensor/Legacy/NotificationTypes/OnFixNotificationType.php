<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnFixNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public $group = 'operator';

    public function __construct()
    {
        $this->identifier = 'on_fix';
        $this->name = ezpI18n::tr('sensor/notification', 'Intervento terminato');
        $this->description = ezpI18n::tr('sensor/notification', "Ricevi una notifica quando un responsabile ha completato l'attività che riguarda una tua segnalazione");
        $this->targets[ParticipantRole::ROLE_AUTHOR] = [];
    }
}