<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

class ParticipantRole extends Exportable
{
    const ROLE_STANDARD = 1;

    const ROLE_OBSERVER = 2;

    const ROLE_OWNER = 3;

    const ROLE_APPROVER = 4;

    const ROLE_AUTHOR = 5;

    public $id;

    public $name;

    public $identifier;
}