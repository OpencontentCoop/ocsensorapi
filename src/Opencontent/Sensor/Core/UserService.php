<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\UserService as UserServiceInterface;

abstract class UserService implements UserServiceInterface
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