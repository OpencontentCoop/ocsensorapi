<?php

namespace Opencontent\Sensor\OpenApi;

use erasys\OpenApi\Spec\v3 as OA;

class ReferenceSchema extends OA\Schema
{
    public $ref;

    public function __construct(OA\Reference $reference = null, array $properties = array())
    {
        if ($reference) {
            $reference->ref = '#/components/schemas/' . $reference->ref;
            $properties = $reference;
        }
        parent::__construct($properties);
    }
}