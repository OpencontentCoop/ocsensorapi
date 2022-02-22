<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnAssignNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_assign';
        $this->name = Translator::translate('Assignment of a issue', 'notification');
        $this->description = Translator::translate('Receive a notification when your issue is assigned to a manager', 'notification');
        $this->setTargets($targets);
    }
}
