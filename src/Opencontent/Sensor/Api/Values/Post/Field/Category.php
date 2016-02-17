<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

class Category extends Field
{
    public $id;

    public $name;

    public $userIdList = array();
}