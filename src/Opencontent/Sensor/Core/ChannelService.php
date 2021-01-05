<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\ChannelService as ChannelServiceInterface;

abstract class ChannelService implements ChannelServiceInterface
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