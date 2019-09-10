<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\SearchService as SearchServiceInterface;

abstract class SearchService implements SearchServiceInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }
}
