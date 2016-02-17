<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\PostService as PostServiceInterface;


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
