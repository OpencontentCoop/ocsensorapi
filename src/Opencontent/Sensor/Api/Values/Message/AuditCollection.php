<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\MessageCollection;

class AuditCollection extends MessageCollection
{
    /**
     * @var Audit[]
     */
    public $messages = array();
}