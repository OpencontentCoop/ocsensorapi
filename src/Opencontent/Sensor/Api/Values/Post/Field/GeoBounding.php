<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

class GeoBounding
{
    public $color;

    public $type;

    public $geoJson;

    public function __construct($data = [])
    {
        if (isset($data['color'])){
            $this->color = $data['color'];
        }

        if (isset($data['type'])){
            $this->type = $data['type'];
        }

        if (isset($data['geo_json'])){
            $this->geoJson = $data['geo_json'];
        }

        if (isset($data['geoJson'])){
            $this->geoJson = $data['geoJson'];
        }
    }
}