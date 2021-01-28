<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\Message;

/**
 * Class Comment
 * @package Opencontent\Sensor\Api\Values\Message
 */
class Comment extends Message
{
    /**
     * @var bool
     */
    public $needModeration = false;

    /**
     * @var bool
     */
    public $isRejected = false;
}