<?php

namespace Opencontent\Sensor\Core;


abstract class AreaService implements \Opencontent\Sensor\Api\AreaService
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