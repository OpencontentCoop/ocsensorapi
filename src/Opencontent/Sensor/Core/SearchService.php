<?php

namespace OpenContent\Sensor\Core;

use OpenContent\Sensor\Api\SearchService as SearchServiceInterface;
use OpenContent\Sensor\Api\Values\Post;

abstract class SearchService implements SearchServiceInterface
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
