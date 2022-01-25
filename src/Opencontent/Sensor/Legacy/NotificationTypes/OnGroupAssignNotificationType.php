<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnGroupAssignNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_group_assign';
        $this->name = Translator::translate('Assigning a issue to a group', 'notification');
        $this->description = Translator::translate('Receive notification when a issue is assigned to your group', 'notification');
        $this->targets[ParticipantRole::ROLE_OWNER] = [Participant::TYPE_GROUP];
    }

}
