<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

abstract class NotificationType extends Exportable
{
    public $identifier;

    public $name;

    public $description;

    public $group = 'standard';

    public $template;

    /**
     * @var array
     */
    protected $targets = [];

    /**
     * @param $role
     * @return array
     */
    public function getTarget($role)
    {
        return isset($this->targets[$role]) ? $this->targets[$role] : [];
    }

    /**
     * @return array
     */
    public function getTargets()
    {
        return $this->targets;
    }

    /**
     * @param $role
     * @param array $types
     * @return void
     */
    public function setTarget($role, array $types)
    {
        $this->targets[$role] = $types;
    }

    /**
     * @param $targets
     * @return void
     */
    public function setTargets($targets)
    {
        if (is_array($targets)){
            foreach ($targets as $role => $types){
                $this->setTarget($role, $types);
            }
        }
    }
}
