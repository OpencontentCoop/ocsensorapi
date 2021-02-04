<?php

namespace Opencontent\Sensor\Legacy\Scenarios\Criteria;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;

class TypeCriterion extends Exportable implements ScenarioCriterion
{
    public $typeList = [];

    public function __construct(array $typeList)
    {
        $this->typeList = $typeList;
    }

    public function getIdentifier()
    {
        return 'type';
    }

    public function match(Post $post)
    {
        if (empty($this->typeList)){
            return true;
        }

        return in_array($post->type->identifier, $this->typeList);
    }

    public function jsonSerialize()
    {
        return $this->typeList;
    }
}