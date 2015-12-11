<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 21/11/15
 * Time: 12:43
 */

namespace OpenContent\Sensor\Api\Values\Post;

use OpenContent\Sensor\Api\Exportable;

class Status extends Exportable
{
    public $identifier;

    public $name;

    public $label;
}