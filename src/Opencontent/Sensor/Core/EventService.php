<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\EventService as EventServiceInterface;

abstract class EventService implements EventServiceInterface
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