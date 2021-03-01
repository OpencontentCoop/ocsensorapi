<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use ezpI18n;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnAddCommentToModerateNotificationType extends NotificationType
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_comment_to_moderate';
        $this->name = ezpI18n::tr('sensor/notification', 'Commento da moderare');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando viene aggiunto un commento da moderare');
        $this->setTemplate();
        $this->targets[ParticipantRole::ROLE_APPROVER] = [Participant::TYPE_USER];
        $this->targets[ParticipantRole::ROLE_AUTHOR] = [];
    }
}