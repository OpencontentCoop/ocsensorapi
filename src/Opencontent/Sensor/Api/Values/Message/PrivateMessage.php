<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\User;

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