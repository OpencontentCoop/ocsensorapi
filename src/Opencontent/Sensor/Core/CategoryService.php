<?php

namespace Opencontent\Sensor\Core;


abstract class CategoryService implements \Opencontent\Sensor\Api\CategoryService
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