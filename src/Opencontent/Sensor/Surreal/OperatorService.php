<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\OperatorService as CoreOperatorService;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;

class OperatorService extends CoreOperatorService
{

    /**
     * @inheritDoc
     */
    public function loadOperator($id, $limitations = null)
    {
        // TODO: Implement loadOperator() method.
    }

    public function loadOperators($query, $limit, $offset, $limitations = null)
    {
        // TODO: Implement loadOperators() method.
    }

    public function loadOperatorsByGroup(Group $group, $limit, $cursor, $limitations = null)
    {
        // TODO: Implement loadOperatorsByGroup() method.
    }

    public function createOperator(array $payload)
    {
        // TODO: Implement createOperator() method.
    }

    public function updateOperator(Operator $operator, array $payload)
    {
        // TODO: Implement updateOperator() method.
    }
}