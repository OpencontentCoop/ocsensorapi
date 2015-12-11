<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 21/11/15
 * Time: 12:45
 */

namespace OpenContent\Sensor\Api\Values\Post;

use OpenContent\Sensor\Api\Exportable;

class Type extends Exportable
{
    public $identifier;

    public $label;

    public $name;
}