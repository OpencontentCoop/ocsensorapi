<?php

namespace Opencontent\Sensor\Core;


abstract class GroupService implements \Opencontent\Sensor\Api\GroupService
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