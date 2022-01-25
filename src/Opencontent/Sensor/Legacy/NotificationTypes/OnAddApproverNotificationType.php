<?php


namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Api\Values\NotificationType;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Legacy\Utils\Translator;

class OnAddApproverNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct()
    {
        $this->identifier = 'on_add_approver';
        $this->name = Translator::translate('Involvement of a reference for the citizen', 'notification');
        $this->description = Translator::translate( 'Receive a notification when your issue is assigned to a new reference for the citizen', 'notification');
        $this->targets[ParticipantRole::ROLE_APPROVER] = [Participant::TYPE_USER, Participant::TYPE_GROUP];
    }
}
