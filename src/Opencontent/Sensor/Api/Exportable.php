<?php

namespace Opencontent\Sensor\Api;


abstract class Exportable implements \JsonSerializable
{
    public static function __set_state($array)
    {
        $object = new static();
        foreach ($array as $key => $value) {
            $object->{$key} = $value;
        }
        return $object;
    }

    public function attributes()
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $attributes = array();
        foreach ($properties as $property)
            $attributes[] = $property->getName();
        return $attributes;
    }

    public function hasAttribute($key)
    {
        return in_array($key, $this->attributes());
    }

    public function attribute($key)
    {
        return $this->{$key};
    }

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        return $this->toJson($objectVars);
    }

    protected static function toJson($objectVars)
    {
        foreach ($objectVars as $identifier => $value) {
            if ($identifier == 'id' && is_scalar($value)) {
                $objectVars[$identifier] = (int)$value;
            }
            if ($value instanceof \DateTime) {
                $objectVars[$identifier] = $value->format('c');
            }
            if ($value instanceof \JsonSerializable) {
                $objectVars[$identifier] = $value->jsonSerialize();
            }
        }

        return $objectVars;
    }
}