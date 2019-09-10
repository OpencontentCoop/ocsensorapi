<?php

namespace Opencontent\Sensor\Api\Exception;

use Exception;

class BaseException extends Exception
{
    public function getServerErrorCode()
    {
        return 400;
    }

    public function getErrorType()
    {
        if ($this->getPrevious() instanceof Exception)
            return self::cleanErrorCode(get_class($this->getPrevious()));
        return self::cleanErrorCode(get_called_class());
    }

    public static function cleanErrorCode($code)
    {
        return str_replace('\\', ".", $code);
    }
}