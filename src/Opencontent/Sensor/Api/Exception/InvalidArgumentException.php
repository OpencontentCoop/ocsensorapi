<?php

namespace Opencontent\Sensor\Api\Exception;


class InvalidArgumentException extends BaseException
{
    public function getServerErrorCode()
    {
        return 400;
    }
}