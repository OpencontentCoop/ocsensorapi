<?php


namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use ezpI18n;

class OnAddApproverNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_approver';
        $this->name = ezpI18n::tr('sensor/notification', 'Coinvolgimento di un riferimento per il cittadino');
        $this->description = ezpI18n::tr('sensor/notification', 'Ricevi una notifica quando una tua segnalazione è assegnata a un nuovo riferimento per il cittadino');
    }
}