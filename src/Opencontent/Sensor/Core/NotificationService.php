<?php

namespace Opencontent\Sensor\Core;


abstract class NotificationService implements \Opencontent\Sensor\Api\NotificationService
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