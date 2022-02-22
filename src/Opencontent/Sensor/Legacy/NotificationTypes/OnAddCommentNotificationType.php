<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\NotificationType;

class OnAddCommentNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_add_comment';
        $this->name = Translator::translate('Comment to a issue', 'notification');
        $this->description = Translator::translate('Receive a notification when a comment is added to your report', 'notification');
        $this->setTargets($targets);
    }

}
