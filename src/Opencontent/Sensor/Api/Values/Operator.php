<?php

namespace Opencontent\Sensor\Api\Values;


class Operator extends User
{
    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['permissions']);
        unset($objectVars['image']);
        unset($objectVars['hasRead']);
        unset($objectVars['firstName']);
        unset($objectVars['lastName']);

        return self::toJson($objectVars);
    }
}