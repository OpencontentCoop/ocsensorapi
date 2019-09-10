<?php

namespace Opencontent\Sensor\Api;

abstract class StatisticFactory
{
    const DEFAULT_INTERVAL = 'yearly';

    protected $parameters;

    abstract public function getIdentifier();

    abstract public function getName();

    abstract public function getDescription();

    abstract public function getData();

    /**
     * @return mixed
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param mixed $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function getParameter($name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function hasParameter($name)
    {
        return isset($this->parameters[$name]);
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function attributes()
    {
        return ['name', 'identifier', 'description'];
    }

    public function hasAttribute($name)
    {
        return in_array($name, $this->attributes());
    }

    public function attribute($name)
    {
        if ($name == 'name'){
            return $this->getName();
        }

        if ($name == 'identifier'){
            return $this->getIdentifier();
        }

        if ($name == 'description'){
            return $this->getDescription();
        }

        return null;
    }
}