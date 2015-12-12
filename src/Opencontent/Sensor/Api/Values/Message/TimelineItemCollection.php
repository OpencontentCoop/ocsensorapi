<?php

namespace OpenContent\Sensor\Api\Values\Message;

use OpenContent\Sensor\Api\Values\MessageCollection;
use OpenContent\Sensor\Api\Values\Message\TimelineItem;

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
    public function getByType( $type )
    {
        $result = new TimelineItemCollection();
        foreach( $this->messages as $message )
        {
            if ( $message->type == $type )
            {
                $result->addMessage( $message );
            }
        }
        return $result;
    }
}