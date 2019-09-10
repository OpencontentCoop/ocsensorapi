<?php

namespace Opencontent\Sensor\Api\Validators;

use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Exception\InvalidInputException;

class PostCreateStructValidator
{
    protected $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function validate(PostCreateStruct $createStruct)
    {
        if (trim($createStruct->subject) == '') {
            throw new InvalidInputException("Field subject is required");
        }

        if (trim($createStruct->description) == '') {
            throw new InvalidInputException("Field description is required");
        }

        if (trim($createStruct->type) == '') {
            throw new InvalidInputException("Field type is required");
        }

        if (trim($createStruct->privacy) == '') {
            throw new InvalidInputException("Field privacy is required");
        }
    }
}