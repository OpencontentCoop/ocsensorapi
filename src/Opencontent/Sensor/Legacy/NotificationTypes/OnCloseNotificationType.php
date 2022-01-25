<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnCloseNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_close';
        $this->name = Translator::translate('Closing a issue', 'notification');
        $this->description = Translator::translate("Receive a notification when your issue has been closed", 'notification');
    }
}
