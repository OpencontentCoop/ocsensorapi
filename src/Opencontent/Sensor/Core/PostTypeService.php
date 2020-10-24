<?php

namespace Opencontent\Sensor\Core;

abstract class PostTypeService implements \Opencontent\Sensor\Api\PostTypeService
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