<?php

namespace Opencontent\Sensor\Api\Exception;


class InvalidInputException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}