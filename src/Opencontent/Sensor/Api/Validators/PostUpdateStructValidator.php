<?php

namespace Opencontent\Sensor\Api\Validators;

use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;

class PostUpdateStructValidator
{
    protected $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function validate(PostUpdateStruct $createStruct)
    {
    }
}