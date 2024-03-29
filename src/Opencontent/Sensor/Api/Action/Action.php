<?php

namespace Opencontent\Sensor\Api\Action;

class Action
{
    public $identifier;

    private $ignorePermissions = false;

    /**
     * @var ActionParameter[]
     */
    public $parameters = array();

    public function __construct($identifier = null, array $parameters = null, $ignorePermissions = false)
    {
        if ($identifier){
            $this->identifier = $identifier;
        }
        if (is_array($parameters)){
            foreach ($parameters as $key => $value){
                $this->setParameter($key, $value);
            }
        }
        $this->ignorePermissions = $ignorePermissions;
    }

    public function hasParameter($name)
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->identifier == $name && $parameter->value !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $name
     *
     * @return mixed|null
     */
    public function getParameterValue($name)
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->identifier == $name && $parameter->value !== null) {
                return $parameter->value;
            }
        }
        return null;
    }

    public function setParameter($identifier, $value)
    {
        $newParameter = new ActionParameter();
        $newParameter->identifier = $identifier;
        $newParameter->value = $value;
        $this->parameters[] = $newParameter;
    }

    public function isIgnorePermissionEnabled()
    {
        return $this->ignorePermissions === true;
    }
}