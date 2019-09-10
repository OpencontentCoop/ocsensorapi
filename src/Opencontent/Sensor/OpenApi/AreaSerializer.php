<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\Category;

class AreaSerializer extends AbstractSerializer
{
    /**
     * @param Area|Category $item
     * @param array $parameters
     *
     * @return array
     */
    public function serialize($item, array $parameters = [])
    {
        $area = $item->jsonSerialize();

        $area['operators'] = (array)$area['operatorsIdList'];
        $area['operators'] = array_map('intval', $area['operators']);
        unset($area['operatorsIdList']);

        $area['groups'] = (array)$area['groupsIdList'];
        $area['groups'] = array_map('intval', $area['groups']);
        unset($area['groupsIdList']);

        return $area;
    }
}