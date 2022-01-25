<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class OnFixNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public $group = 'operator';

    public function __construct()
    {
        $this->identifier = 'on_fix';
        $this->name = Translator::translate('Issue fixed', 'notification');
        $this->description = Translator::translate("Receive a notification when a operator has completed the activity concerning your issue", 'notification');
        $this->targets[ParticipantRole::ROLE_AUTHOR] = [];
    }
}
