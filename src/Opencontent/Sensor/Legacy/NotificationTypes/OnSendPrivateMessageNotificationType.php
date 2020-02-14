<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnSendPrivateMessageNotificationType extends NotificationType
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_send_private_message';
        $this->name = ezpI18n::tr('sensor/notification', 'Messaggio privato');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando sei il destinatario di un messaggio privato');
        $this->setTemplate();
    }
}