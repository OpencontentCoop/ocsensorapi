<?php

namespace Opencontent\Sensor\Api\Exception;

use Opencontent\Sensor\Api\Values\Post;

class DuplicateUuidException extends BaseException
{
    private $post;

    private $uuid;

    public function __construct($uuid)
    {
        $this->uuid = $uuid;
        $errorMessage = "The uuid $uuid is already in use";
        parent::__construct($errorMessage);
    }

    public function getServerErrorCode()
    {
        return 400;
    }

    /**
     * @return Post
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @param Post $post
     */
    public function setPost($post)
    {
        $this->post = $post;
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param mixed $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }
}