<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\AreaService as CoreAreaService;
use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;

class AreaService extends CoreAreaService
{
    /**
     * @inheritDoc
     */
    public function loadArea($areaId)
    {
        // TODO: Implement loadArea() method.
    }

    public function loadAreas($query, $limit, $offset, $excludeMainArea = false)
    {
        // TODO: Implement loadAreas() method.
    }

    public function createArea($struct)
    {
        // TODO: Implement createArea() method.
    }

    public function updateArea(Area $area, $struct)
    {
        // TODO: Implement updateArea() method.
    }

    public function removeArea($areaId)
    {
        // TODO: Implement removeArea() method.
    }

    /**
     * @inheritDoc
     */
    public function findAreaByGeoLocation(GeoLocation $geoLocation)
    {
        // TODO: Implement findAreaByGeoLocation() method.
    }

    /**
     * @inheritDoc
     */
    public function disableCategories(Area $area, $categoryIdList)
    {
        // TODO: Implement disableCategories() method.
    }
}