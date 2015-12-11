<?php

namespace OpenContent\Sensor\Api\Values\Message;

use OpenContent\Sensor\Api\Values\MessageCollection;
use OpenContent\Sensor\Api\Values\Message;

class PrivateMessageCollection extends MessageCollection
{
    /**
     * @var Message\PrivateMessage[]
     */
    public $messages = array();
}