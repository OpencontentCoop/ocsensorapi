<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post\Field\Area;

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
}