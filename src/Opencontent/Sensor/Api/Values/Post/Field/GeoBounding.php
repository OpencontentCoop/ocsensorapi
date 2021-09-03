<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Exportable;

class GeoBounding extends Exportable
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

    public function jsonSerialize()
    {
        $data = (array)$this;

        return empty($data) ? null : $data;
    }
}