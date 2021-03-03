<?php

namespace Opencontent\Sensor\Legacy\NotificationTypes;

use SensorNotificationTextHelper;

trait TemplateTextHelperTrait
{
    public $identifier;

    public $template;

    public function initTemplate()
    {
        if ($this->template === null) {
            $notificationTexts = SensorNotificationTextHelper::getTemplates();
            if (!isset($notificationTexts[$this->identifier])) {
                $notificationTexts = SensorNotificationTextHelper::getDefaultTemplates();
            }
            if (isset($notificationTexts[$this->identifier])) {
                $this->template = $notificationTexts[$this->identifier];
            }
        }
    }
}