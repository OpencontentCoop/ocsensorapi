<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Sensor\Api\Exception\NotFoundException;


trait ContentSearchTrait
{
    protected $environmentSettings;

    abstract public function getClassIdentifierAsString();

    abstract public function getSubtreeAsString();

    /**
     * @return \SensorDefaultEnvironmentSettings
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function getEnvironmentSettings()
    {
        if ($this->environmentSettings === null) {
            return new \SensorDefaultEnvironmentSettings();
        }

        return $this->environmentSettings;
    }

    /**
     * @param EnvironmentSettings $environmentSettings
     */
    public function setEnvironmentSettings($environmentSettings)
    {
        $this->environmentSettings = $environmentSettings;
    }

    /**
     * @param $query
     * @param null $limitations
     * @return array|mixed|SearchResults
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function search($query, $limitations = null)
    {
        $query = 'classes [' . $this->getClassIdentifierAsString() . '] and subtree [' . $this->getSubtreeAsString() . '] and ' . $query;
        $search = new ContentSearch();
        $search->setCurrentEnvironmentSettings($this->getEnvironmentSettings());

        return $search->search($query, $limitations);
    }

    /**
     * @param $identifier
     * @param null $limitations
     * @return array
     * @throws NotFoundException
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function searchOne($identifier, $limitations = null)
    {
        $object = \eZContentObject::fetch((int)$identifier);
        $classes = array_map('trim', explode(',', $this->getClassIdentifierAsString()));
        if ($object instanceof \eZContentObject && in_array($object->attribute('class_identifier'), $classes)){
            if ($limitations === null && !$object->canRead()){
                throw new NotFoundException();
            }

            return $this->getEnvironmentSettings()->filterContent(Content::createFromEzContentObject($object));
        }

        throw new NotFoundException();
    }
}
