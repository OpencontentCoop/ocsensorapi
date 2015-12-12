<?php

namespace OpenContent\Sensor\Api\Values;

use DateTime;
use OpenContent\Sensor\Api\Exportable;

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