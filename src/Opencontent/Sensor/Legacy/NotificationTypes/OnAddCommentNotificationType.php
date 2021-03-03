<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use ezpI18n;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnAddCommentNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_comment';
        $this->name = ezpI18n::tr('sensor/notification', 'Commento a una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando viene aggiunto un commento ad una tua segnalazione');
        $this->targets[ParticipantRole::ROLE_OBSERVER] = [Participant::TYPE_USER];
    }

}