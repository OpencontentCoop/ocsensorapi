<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Values\PermissionCollection;
use Opencontent\Sensor\Api\Exportable;
use DateTime;

class User extends Exportable
{
    public $id;

    public $name;

    public $email;

    /**
     * @var PermissionCollection
     */
    public $permissions;

    /**
     * @var DateTime
     */
    public $lastAccessDateTime;

    public $hasRead = 0;

    public $moderationMode;

    public $commentMode;

    public $behalfOfMode;

    public $isEnabled;

    public $image;

}
