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

    public static function create($identifier, Post $post, User $user, array $parameters = [])
    {
        $event = new static();
        $event->identifier = $identifier;
        $event->post = $post;
        $event->user = $user;
        $event->parameters = $parameters;

        return $event;
    }
}