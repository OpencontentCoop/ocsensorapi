<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\MessageCollection;
use Opencontent\Sensor\Api\Values\Message;

class PrivateMessageCollection extends MessageCollection
{
    /**
     * @var Message\PrivateMessage[]
     */
    public $messages = array();
}