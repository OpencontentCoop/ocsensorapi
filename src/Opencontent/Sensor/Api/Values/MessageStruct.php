<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

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