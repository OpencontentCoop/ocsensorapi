<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Values\Message;
use DateTime;
use Opencontent\Sensor\Api\Collection;

class MessageCollection extends Collection
{
    /**
     * @var int
     */
    public $count = 0;

    /**
     * @var Message
     */
    public $lastMessage;

    /**
     * @var Message[]
     */
    public $messages = array();

    public function unreadMessages(DateTime $lastAccessDateTime)
    {
        $unreadMessages = array();
        foreach ($this->messages as $message) {
            if ($message->modified > $lastAccessDateTime) {
                $unreadMessages[] = $message;
            }
        }

        return $unreadMessages;
    }

    public function addMessage(Message $message)
    {
        $this->messages[] = $message;
        $this->lastMessage = $message;
        $this->count++;
    }

    protected function toArray()
    {
        return (array)$this->messages;
    }

    protected function fromArray(array $data)
    {
        $this->messages = $data;
    }

    public function jsonSerialize()
    {
        return self::toJson(array_values($this->messages));
    }
}