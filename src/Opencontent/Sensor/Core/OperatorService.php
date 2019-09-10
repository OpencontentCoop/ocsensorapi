<?php

namespace Opencontent\Sensor\Core;


abstract class OperatorService implements \Opencontent\Sensor\Api\OperatorService
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