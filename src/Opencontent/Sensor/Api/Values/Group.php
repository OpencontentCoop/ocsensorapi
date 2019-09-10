<?php

namespace Opencontent\Sensor\Api\Values;

class Group
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    public $email;

    public function __toString()
    {
        return '' . $this->id;
    }
}