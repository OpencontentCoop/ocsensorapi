<?php

namespace OpenContent\Sensor\Api\Values;
use OpenContent\Sensor\Api\Values\PermissionCollection;
use OpenContent\Sensor\Api\Exportable;
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

    public $moderationMode;

    public $commentMode;

    public $behalfOfMode;

    public $isEnabled;

    public $image;

}
