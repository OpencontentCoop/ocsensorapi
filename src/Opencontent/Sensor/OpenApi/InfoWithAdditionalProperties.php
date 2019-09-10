<?php

namespace Opencontent\Sensor\OpenApi;

use erasys\OpenApi\Spec\v3 as OA;

class InfoWithAdditionalProperties extends OA\Info
{
    public $xApiId;
    public $xAudience;
}