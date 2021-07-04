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

    public function getType()
    {
        $classNameWithNamespace = get_class($this);
        $name = substr($classNameWithNamespace, strrpos($classNameWithNamespace, '\\')+1);
        return strtolower(str_replace('Struct', '', $name));
    }
}