<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\MessageService as MessageServiceInterface;


abstract class MessageService implements MessageServiceInterface
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