<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;

interface OperatorService
{
    /**
     * @param $id
     * @return Operator
     */
    public function loadOperator($id, $limitations = null);

    public function loadOperators($query, $limit, $offset, $limitations = null);

    public function loadOperatorsByGroup(Group $group, $limit, $cursor, $limitations = null);

    public function createOperator(array $payload);

    public function updateOperator(Operator $operator, array $payload);
}
