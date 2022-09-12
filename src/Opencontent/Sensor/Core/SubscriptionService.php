<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\SubscriptionService as SubscriptionServiceInterface;

abstract class SubscriptionService implements SubscriptionServiceInterface
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