<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Sensor\Api\Exception\NotFoundException;


trait ContentSearchTrait
{
    protected $environmentSettings;

    abstract protected function getClassIdentifierAsString();

    abstract protected function getSubtreeAsString();

    /**
     * @return \DefaultEnvironmentSettings
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function getEnvironmentSettings()
    {
        if ($this->environmentSettings === null) {
            return new \DefaultEnvironmentSettings();
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
     * @param $query
     * @param null $limitations
     * @return array
     * @throws NotFoundException
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function searchOne($query, $limitations = null)
    {
        $query .= ' limit 1';
        $results = $this->search($query, $limitations);
        if ($results->totalCount > 0) {
            return $results->searchHits[0];
        }

        throw new NotFoundException();
    }
}