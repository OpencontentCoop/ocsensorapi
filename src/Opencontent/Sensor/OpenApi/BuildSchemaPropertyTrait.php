<?php

namespace Opencontent\Sensor\OpenApi;

trait BuildSchemaPropertyTrait
{
    protected static function buildSchemaProperty($properties)
    {
        $schema = new ReferenceSchema();
        foreach ($properties as $key => $value) {
            $schema->{$key} = $value;
        }

        return $schema;
    }
}