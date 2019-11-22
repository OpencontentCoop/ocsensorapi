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

    /**
     * @var integer[]
     */
    public $operatorsIdList = array();

    /**
     * @var integer[]
     */
    public $groupsIdList = array();

    /**
     * @var integer[]
     */
    public $observersIdList = array();

    public function __toString()
    {
        return '' . $this->id;
    }
}