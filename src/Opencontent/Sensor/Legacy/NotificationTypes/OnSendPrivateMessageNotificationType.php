<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnSendPrivateMessageNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public $group = 'operator';

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_send_private_message';
        $this->name = Translator::translate('Private message', 'notification');
        $this->description = Translator::translate('Receive a notification when you are the recipient of a private message', 'notification');
        $this->setTargets($targets);
    }
}
