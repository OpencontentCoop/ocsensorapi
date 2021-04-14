<?php

namespace Opencontent\Sensor\Api\Exception;


class ForbiddenException extends BaseException
{
    public function getServerErrorCode()
    {
        return 403;
    }

}