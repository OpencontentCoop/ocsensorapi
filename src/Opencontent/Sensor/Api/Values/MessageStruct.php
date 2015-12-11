<?php

namespace OpenContent\Sensor\Api\Values;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class MessageStruct
{
    /**
     * @var Post
     */
    public $post;

    /**
     * @var \DateTime
     */
    public $createdDateTime;

    /**
     * @var User
     */
    public $creator;

    public $text;

    public $id;
}