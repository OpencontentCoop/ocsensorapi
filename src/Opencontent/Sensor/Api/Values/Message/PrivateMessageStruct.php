<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\MessageStruct;

class PrivateMessageStruct extends MessageStruct
{
    public $receiverIdList = [];

    public $isResponseProposal = false;
}