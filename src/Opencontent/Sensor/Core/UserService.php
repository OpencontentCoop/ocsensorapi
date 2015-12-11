<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 24/11/15
 * Time: 12:51
 */

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