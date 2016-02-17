<?php

namespace Opencontent\Sensor\Api\Values;

use DateTime;
use Opencontent\Sensor\Api\Exportable;

abstract class Message extends Exportable
{
    public $id;

    /**
     * @var DateTime
     */
    public $published;

    /**
     * @var DateTime
     */
    public $modified;

    /**
     * @var User
     */
    public $creator;

    /**
     * @var string
     */
    public $text;

}