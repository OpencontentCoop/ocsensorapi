<?php

namespace Opencontent\Sensor\OpenApi;


use Opencontent\Sensor\Api\Values\Message;

class MessageSerializer extends AbstractSerializer
{
    /**
     * @param Message $item
     * @param array $parameters
     *
     * @return array
     */
    public function serialize($item, array $parameters = [])
    {
        $message = [
            'id' => (int)$item->id,
            'published_at' => $this->formatDate($item->published),
            'modified_at' => $this->formatDate($item->modified),
            'creator' => (int)$item->creator->id,
            'text' => $item->text
        ];

        if ($item instanceof Message\PrivateMessage) {
            $receivers = [];
            foreach ($item->receivers as $receiver) {
                $receivers[] = (int)$receiver->id;
            }

            $message['receivers'] = $receivers;
        }

        return $message;
    }

}