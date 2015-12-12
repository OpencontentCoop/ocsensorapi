<?php

namespace OpenContent\Sensor\Api\Values\Post;

use \DateTime;
use OpenContent\Sensor\Api\Exportable;

class ResolutionInfo extends Exportable
{
    /**
     * @var DateTime
     */
    public $resolutionDateTime;

    /**
     * @var DateTime
     */
    public $creationDateTime;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $text;

    /**
     * @var int
     */
    public $days;
}