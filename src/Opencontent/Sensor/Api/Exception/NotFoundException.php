<?php

namespace Opencontent\Sensor\Api\Exception;


class NotFoundException extends BaseException
{
    public function __construct($message = "", $code = 0)
    {
        $message = "The requested content does not exist or is not accessible";
        parent::__construct($message, $code);
    }

    public function getServerErrorCode()
    {
        return 404;
    }
}