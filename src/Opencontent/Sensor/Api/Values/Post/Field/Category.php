<?php

namespace OpenContent\Sensor\Api\Values\Post\Field;

use OpenContent\Sensor\Api\Values\Post\Field;

class Category extends Field
{
    public $id;

    public $name;

    public $userIdList = array();
}