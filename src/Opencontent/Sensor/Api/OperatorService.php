<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Operator;

interface OperatorService
{
    /**
     * @param $id
     * @return Operator
     */
    public function loadOperator($id, $limitations = null);

    public function loadOperators($query, $limit, $offset, $limitations = null);
}