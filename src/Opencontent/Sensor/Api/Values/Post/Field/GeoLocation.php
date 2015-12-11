<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 21/11/15
 * Time: 12:46
 */

namespace OpenContent\Sensor\Api\Values\Post\Field;

use OpenContent\Sensor\Api\Values\Post\Field;

class GeoLocation extends Field
{
    public $latitude;

    public $longitude;

    public $address;
}