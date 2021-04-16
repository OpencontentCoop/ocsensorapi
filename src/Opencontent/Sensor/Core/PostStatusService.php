<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\PostStatusService as PostStatusServiceInterface;

abstract class PostStatusService implements PostStatusServiceInterface
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