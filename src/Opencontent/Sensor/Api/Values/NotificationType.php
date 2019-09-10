<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

class NotificationType extends Exportable
{
    public $identifier;

    public $name;

    public $description;

    public $group = 'standard';

    public $template;
}