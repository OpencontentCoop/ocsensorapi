<?php

namespace Opencontent\Sensor\Api\Exception;


class UnexpectedException extends BaseException
{
    public function getServerErrorCode()
    {
        return 500;
    }
}