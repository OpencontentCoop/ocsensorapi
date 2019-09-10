<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\Message;

/**
 * Class TimelineItem
 * @package Opencontent\Sensor\Api\Values\Message
 */
class TimelineItem extends Message
{
    /**
     * @var string
     */
    public $type;

    public $extra = array();

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['extra']);

        return self::toJson($objectVars);
    }
}