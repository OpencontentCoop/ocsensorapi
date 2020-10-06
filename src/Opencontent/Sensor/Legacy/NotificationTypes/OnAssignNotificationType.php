<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnAssignNotificationType extends NotificationType
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_assign';
        $this->name = ezpI18n::tr('sensor/notification', 'Assegnazione di una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando una tua segnalazione è assegnata a un responsabile');
        $this->setTemplate();
        $this->targets[ParticipantRole::ROLE_OWNER] = [Participant::TYPE_USER];
    }
}