<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\NotificationType;

class OnAddCommentToModerateNotificationType extends NotificationType implements TemplateAwareNotificationTypeInterface
{
    use TemplateTextHelperTrait;

    public $group = 'operator';

    public function __construct(array $targets = null)
    {
        $this->identifier = 'on_add_comment_to_moderate';
        $this->name = Translator::translate('Comment to moderate', 'notification');
        $this->description = Translator::translate('Receive a notification when a comment is added to moderate', 'notification');
        $this->setTargets($targets);
    }
}
