<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnAddCommentNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_comment';
        $this->name = Translator::translate('Comment to a issue', 'notification');
            $this->description = Translator::translate('Receive a notification when a comment is added to your report', 'notification');
        $this->targets[ParticipantRole::ROLE_OBSERVER] = [Participant::TYPE_USER];
    }

}
