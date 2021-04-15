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

        return $area;
    }
}