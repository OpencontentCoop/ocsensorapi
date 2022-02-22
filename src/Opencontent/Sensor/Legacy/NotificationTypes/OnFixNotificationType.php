<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnFixNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public $group = 'operator';

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_fix';
        $this->name = Translator::translate('Issue fixed', 'notification');
        $this->description = Translator::translate("Receive a notification when a operator has completed the activity concerning your issue", 'notification');
        $this->setTargets($targets);
    }
}
