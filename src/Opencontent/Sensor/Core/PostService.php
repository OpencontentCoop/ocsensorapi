<?php

namespace OpenContent\Sensor\Core;

use OpenContent\Sensor\Api\PostService as PostServiceInterface;


abstract class PostService implements PostServiceInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @param Repository $repository
     */
    public function __construct( Repository $repository )
    {
        $this->repository = $repository;
    }
}
