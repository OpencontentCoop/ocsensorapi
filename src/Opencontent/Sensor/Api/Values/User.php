<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Values\PermissionCollection;
use Opencontent\Sensor\Api\Exportable;
use DateTime;

/**
 * Class User
 * @package Opencontent\Sensor\Api\Values
 */
class User extends Exportable
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $fiscalCode;

    /**
     * @var PermissionCollection
     */
    public $permissions;

    /**
     * @var \DateTime
     */
    public $lastAccessDateTime;

    public $hasRead = 0;

    /**
     * @var bool
     */
    public $moderationMode;

    /**
     * @var bool
     */
    public $commentMode;

    /**
     * @var bool
     */
    public $behalfOfMode;

    /**
     * @var bool
     */
    public $isEnabled;

    public $image;

    /**
     * @var string
     */
    public $type;

    /**
     * @var array
     */
    public $groups;

    /**
     * @var string
     */
    public $phone;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['permissions']);
        unset($objectVars['image']);
        unset($objectVars['hasRead']);

        return self::toJson($objectVars);
    }

}
