<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;

interface AreaService
{
    /**
     * @param $areaId
     * @return Area
     */
    public function loadArea($areaId);

    public function loadAreas($query, $limit, $offset);

    public function createArea($struct);

    public function updateArea(Area $area, $struct);

    public function removeArea($areaId);

    /**
     * @param GeoLocation $geoLocation
     * @return Area|null
     */
    public function findAreaByGeoLocation(GeoLocation $geoLocation);

    /**
     * @param Area $area
     * @param $categoryIdList
     * @return bool
     */
    public function disableCategories(Area $area, $categoryIdList);
}