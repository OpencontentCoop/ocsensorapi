<?php

namespace Opencontent\Sensor\Api\Exception;


class NotFoundException extends BaseException
{
    public function __construct($message = "", $code = 0)
    {
        $errorMessage = "The requested content does not exist or is not accessible";
        if (!empty($message)) {
            $errorMessage .= ": " . $message;
        }
        parent::__construct($errorMessage, $code);
    }

    public function getServerErrorCode()
    {
        return 404;
    }
}