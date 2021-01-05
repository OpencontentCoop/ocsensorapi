<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Values\Post;

class PostSerializer extends AbstractSerializer
{
    /**
     * @param Post $post
     * @param array $parameters
     * @return array
     */
    public function serialize($post, array $parameters = [])
    {
        $images = [];
        foreach ($post->images as $image){
            $images[] = $image->jsonSerialize()['apiUrl'];
        }

        return $this->apiSettings->replacePlaceholders([
            'id' => (int)$post->id,
            'published_at' => $this->formatDate($post->published),
            'modified_at' => $this->formatDate($post->modified),
            'expiry_at' => $this->formatDate(@$post->expirationInfo->expirationDateTime),
            'closed_at' => $this->formatDate(@$post->resolutionInfo->resolutionDateTime),
            'subject' => $post->subject,
            'description' => $post->description,
            'address' => $post->geoLocation,
            'type' => $post->type->identifier,
            'status' => $post->status->identifier,
            'privacy_status' => $post->privacy->identifier,
            'moderation_status' => $post->moderation->identifier,
            'author' => (int)$post->author->id,
            'reporter' => (int)$post->reporter->id,
            'image' => isset($images[0]) ? $images[0] : null,
            'images' => $images,
            'is_comments_allowed' => $post->commentsIsOpen,
            'address_meta_info' => $post->meta,
            'channel' => $post->channel instanceof Post\Channel ? $post->channel->name : null,
        ]);
    }
}