<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnAssignNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_assign';
        $this->name = Translator::translate('Assignment of a issue', 'notification');
        $this->description = Translator::translate('Receive a notification when your issue is assigned to a manager', 'notification');
        $this->targets[ParticipantRole::ROLE_OWNER] = [Participant::TYPE_USER];
    }
}
