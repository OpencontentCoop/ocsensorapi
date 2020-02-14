<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnAddCommentNotificationType extends NotificationType
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_comment';
        $this->name = ezpI18n::tr('sensor/notification', 'Commento a una segnalazione');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando viene aggiunto un commento ad una tua segnalazione');
        $this->setTemplate();
    }
}