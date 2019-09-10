<?php

namespace Opencontent\Sensor\Api\Values\Post;

use Opencontent\Sensor\Api\Exportable;

/**
 * Class Type
 * @package Opencontent\Sensor\Api\Values\Post
 */
class Type extends Exportable
{
    /**
     * @var string
     */
    public $identifier;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $name;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['label']);

        return self::toJson($objectVars);
    }
}