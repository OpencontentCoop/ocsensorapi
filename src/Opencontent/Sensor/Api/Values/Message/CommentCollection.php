<?php

namespace Opencontent\Sensor\Api\Values\Message;

use Opencontent\Sensor\Api\Values\MessageCollection;

class CommentCollection extends MessageCollection
{
    /**
     * @var Comment[]
     */
    public $messages = array();

    public function commentsToModerate()
    {
        $comments = array();
        foreach ($this->messages as $message) {
            if ($message->needModeration) {
                $comments[] = $message;
            }
        }

        return $comments;
    }
}