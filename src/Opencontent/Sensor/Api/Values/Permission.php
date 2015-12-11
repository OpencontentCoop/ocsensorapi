<?php

namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Exportable;

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