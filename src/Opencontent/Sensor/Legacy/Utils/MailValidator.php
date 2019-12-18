<?php

namespace Opencontent\Sensor\Legacy\Utils;


class MailValidator
{
    public static function validate($address)
    {
        if (strpos($address, '@invalid.email')){
            return false;
        }

        return \eZMail::validate($address);
    }
}