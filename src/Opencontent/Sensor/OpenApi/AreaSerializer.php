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

        if ($item instanceof Area && isset($area['geoBounding']['geoJson']['features'][0]['properties'])) {
            $area['geoBounding']['geoJson']['features'][0]['properties'] = array_merge(
                $area['geoBounding']['geoJson']['features'][0]['properties'],
                [
                    'name' => $item->name,
                    'link' => rtrim($this->apiSettings->siteUrl, '/') . '/?area=' . $item->id,
                ]
            );
        }

        return $area;
    }
}