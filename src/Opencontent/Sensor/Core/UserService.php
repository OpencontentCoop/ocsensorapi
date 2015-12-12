<?php

namespace OpenContent\Sensor\Core;

use OpenContent\Sensor\Api\UserService as UserServiceInterface;

abstract class UserService implements UserServiceInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @param Repository $repository
     */
    public function __construct( Repository $repository )
    {
        $this->repository = $repository;
    }
}