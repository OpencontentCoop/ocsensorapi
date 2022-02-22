<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnCreateNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_create';
        $this->name = Translator::translate('Creating a issue', 'notification');
        $this->description = Translator::translate('Receive a notification for creating a issue', 'notification');
        $this->setTargets($targets);
    }
}
