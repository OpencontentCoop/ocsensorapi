<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnAddObserverNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_observer';
        $this->name = Translator::translate('Involvement of an observer', 'notification');
        $this->description = Translator::translate('Receive a notification when an observer is involved in a report', 'notification');
    }
}
