<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\Message as MessageStruct;

class MessageSerializer
{
    public function serialize(Post $post, Message $message, $remoteUserId = null): MessageStruct
    {
        $visibility = 'internal';
        if ($message instanceof Message\Comment || $message instanceof Message\Response) {
            $visibility = 'applicant';
        }

        $prefix = '';
        $createdAt = $message->published->format('d/m/Y H:i');
        //$prefix = '[' . $createdAt . ' - ' . $message->creator->name . ']: ';
        $author = null;
        if ($post->author->id == $message->creator->id && $remoteUserId) {
//            $prefix = '';
            $author = $remoteUserId;
        }

        $data = [
            'message' => $prefix . $message->richText, //$message->text
            'visibility' => $visibility,
            'sent_at' => $message->published->format('c'),
            'external_id' => $message->id
        ];

        if ($author) {
            $data['author_id'] = $author;
        }

        return MessageStruct::fromArray($data);
    }
}