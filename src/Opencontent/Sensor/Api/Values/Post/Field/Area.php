<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

/**
 * Class Area
 * @package Opencontent\Sensor\Api\Values\Post\Field
 */
class Area extends Field
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var GeoLocation
     */
    public $geo;

    /**
     * @var GeoBounding
     */
    public $geoBounding;

    public function __toString()
    {
        return '' . $this->id;
    }
}