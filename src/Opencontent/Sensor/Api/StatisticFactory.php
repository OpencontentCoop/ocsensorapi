<?php

namespace Opencontent\Sensor\Api;

abstract class StatisticFactory
{
    const DEFAULT_INTERVAL = 'yearly';

    protected $parameters;

    protected $authorFiscalCode;

    protected $renderSettings = [
        'use_highstock' => false
    ];

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
        return isset($this->parameters[$name]) && !empty($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function hasParameter($name)
    {
        return isset($this->parameters[$name]) && !empty($this->parameters[$name]);
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function attributes()
    {
        return ['name', 'identifier', 'description', 'render_settings'];
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

        if ($name == 'render_settings'){
            return $this->renderSettings;
        }

        return null;
    }

    /**
     * @return string
     */
    public function getAuthorFiscalCode()
    {
        return $this->authorFiscalCode;
    }

    /**
     * @param string $authorFiscalCode
     */
    public function setAuthorFiscalCode($authorFiscalCode)
    {
        $this->authorFiscalCode = $authorFiscalCode;
    }

}