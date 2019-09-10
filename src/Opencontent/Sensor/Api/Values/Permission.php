<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

/**
 * Class Permission
 * @package Opencontent\Sensor\Api\Values
 */
class Permission extends Exportable
{
    /**
     * @var string
     */
    public $identifier;

    /**
     * @var bool
     */
    public $grant;
}