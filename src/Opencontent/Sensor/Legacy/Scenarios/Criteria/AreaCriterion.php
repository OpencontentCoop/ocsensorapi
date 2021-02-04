<?php

namespace Opencontent\Sensor\Legacy\Scenarios\Criteria;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;

class AreaCriterion extends Exportable implements ScenarioCriterion
{
    public $idList = [];

    public function __construct(array $idList)
    {
        $this->idList = $idList;
    }

    public function getIdentifier()
    {
        return 'area';
    }

    public function match(Post $post)
    {
        if (empty($this->idList)){
            return true;
        }

        foreach ($post->areas as $category){
            if (!in_array($category->id, $this->idList)){
                return false;
            }
        }

        return true;
    }

    public function jsonSerialize()
    {
        return $this->idList;
    }
}