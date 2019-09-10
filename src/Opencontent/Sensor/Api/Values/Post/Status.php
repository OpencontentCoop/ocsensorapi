<?php

namespace Opencontent\Sensor\Api\Values\Post;

use Opencontent\Sensor\Api\Exportable;

/**
 * Class Status
 * @package Opencontent\Sensor\Api\Values\Post
 */
class Status extends Exportable
{
    /**
     * @var string
     */
    public $identifier;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $label;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['label']);

        return self::toJson($objectVars);
    }
}