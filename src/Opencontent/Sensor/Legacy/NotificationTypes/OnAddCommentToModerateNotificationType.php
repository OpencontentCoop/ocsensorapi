<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnAddCommentToModerateNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public $group = 'operator';

    public function __construct()
    {
        $this->identifier = 'on_add_comment_to_moderate';
        $this->name = Translator::translate('Comment to moderate', 'notification');
        $this->description = Translator::translate('Receive a notification when a comment is added to moderate', 'notification');
        $this->targets[ParticipantRole::ROLE_APPROVER] = [Participant::TYPE_USER];
        $this->targets[ParticipantRole::ROLE_AUTHOR] = [];
    }
}
