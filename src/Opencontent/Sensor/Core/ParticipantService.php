<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\ParticipantService as ParticipantServiceInterface;

abstract class ParticipantService implements ParticipantServiceInterface
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
