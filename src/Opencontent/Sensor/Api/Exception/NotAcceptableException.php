<?php

namespace Opencontent\Sensor\Api\Exception;

class NotAcceptableException extends BaseException
{
    public function getServerErrorCode()
    {
        return 406;
    }
}