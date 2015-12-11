<?php

namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class Event
{
    /**
     * @var string
     */
    public $identifier;

    /**
     * @var Post
     */
    public $post;

    /**
     * @var User
     */
    public $user;

    /**
     * @var array
     */
    public $parameters = array();
}