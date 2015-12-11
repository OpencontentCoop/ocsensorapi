<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 21/11/15
 * Time: 12:57
 */

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