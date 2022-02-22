<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnReopenNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_reopen';
        $this->name = Translator::translate('Reopening a report', 'notification');
        $this->description = Translator::translate("Receive a notification to the reopening of your issue", 'notification');
        $this->setTargets($targets);
    }
}
