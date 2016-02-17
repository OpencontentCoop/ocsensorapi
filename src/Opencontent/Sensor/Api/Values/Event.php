<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

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