<?php

namespace Opencontent\Sensor\Core;

abstract class StatisticsService implements \Opencontent\Sensor\Api\StatisticsService
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