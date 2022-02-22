<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class ReminderNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct(array $targets = null)
    {
        $this->identifier = 'reminder';
        $this->name = Translator::translate('Update notification', 'notification');
        $this->description = Translator::translate("Receive a periodic update notification", 'notification');
        $this->setTargets($targets);
    }
}
