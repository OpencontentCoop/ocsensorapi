<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\MessageCollection;
use Opencontent\Sensor\Api\Values\Message\TimelineItem;

class TimelineItemCollection extends MessageCollection
{
    /**
     * @var TimelineItem[]
     */
    public $messages = array();

    /**
     * @param $type
     *
     * @return TimelineItemCollection
     */
    public function getByType($type)
    {
        $result = new TimelineItemCollection();
        foreach ($this->messages as $message) {
            if ($message->type == $type) {
                $result->addMessage($message);
            }
        }
        return $result;
    }
}