<?php

namespace OpenContent\Sensor\Api\Values\Message;

use OpenContent\Sensor\Api\Values\Message;
use OpenContent\Sensor\Api\Values\User;

class PrivateMessage extends Message
{
    /**
     * @var User[]
     */
    public $receivers;

    public function getReceiverById( $id )
    {
        return isset( $this->receivers[$id] ) ? $this->receivers[$id] : false;
    }
}