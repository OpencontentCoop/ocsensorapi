<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\User;

/**
 * Class PrivateMessage
 * @package Opencontent\Sensor\Api\Values\Message
 */
class PrivateMessage extends Message
{
    /**
     * @var User[]
     */
    public $receivers;

    public function getReceiverById($id)
    {
        foreach ($this->receivers as $receiver) {
            if ($receiver instanceof Participant && $receiver->id == $id) {
                return $receiver;
            }

        }

        return false;
    }

    public function getReceiverByIdList($idList)
    {
        foreach ($this->receivers as $receiver) {
            if ($receiver instanceof Participant && in_array($receiver->id, $idList)) {
                return $receiver;
            }
        }

        return false;
    }
}