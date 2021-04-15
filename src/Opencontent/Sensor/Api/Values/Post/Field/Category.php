<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

/**
 * Class Category
 * @package Opencontent\Sensor\Api\Values\Post\Field
 */
class Category extends Field
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    public function __toString()
    {
        return '' . $this->id;
    }
}