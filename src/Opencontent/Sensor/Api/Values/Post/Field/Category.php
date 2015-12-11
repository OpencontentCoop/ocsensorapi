<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 21/11/15
 * Time: 12:46
 */

namespace OpenContent\Sensor\Api\Values\Post\Field;

use OpenContent\Sensor\Api\Values\Post\Field;

class Category extends Field
{
    public $id;

    public $name;

    public $userIdList = array();
}