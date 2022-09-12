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
    public $userType;
    
    /**
     * @var string
     */
    public $firstName;

    /**
     * @var string
     */
    public $lastName;

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
    public $groups = [];

    /**
     * @var string
     */
    public $phone;

    /**
     * @var string
     */
    public $language = 'ita-IT';

    /**
     * @var bool
     */
    public $restrictMode;

    /**
     * @var bool
     */
    public $isFirstApprover = false;

    public $firstApproverHasRead = 0;

    public $firstApproverLastAccessDateTime;

    /**
     * @var bool
     */
    public $isSuperObserver = false;

    /**
     * @var bool
     */
    public $isSuperUser = false;

    /**
     * @var array
     */
    public $userGroups = [];

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['permissions']);
        unset($objectVars['image']);
        unset($objectVars['hasRead']);
        unset($objectVars['isFirstApprover']);
        unset($objectVars['firstApproverHasRead']);
        unset($objectVars['firstApproverLastAccessDateTime']);

        return self::toJson($objectVars);
    }

}
