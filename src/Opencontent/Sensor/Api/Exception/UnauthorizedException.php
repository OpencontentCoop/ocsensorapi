<?php

namespace Opencontent\Sensor\Api\Exception;


class UnauthorizedException extends BaseException
{
    public function getServerErrorCode()
    {
        return 403;
    }

}