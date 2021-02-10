<?php

namespace Opencontent\Sensor\Legacy\Scenarios\Criteria;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;
use Opencontent\Sensor\Legacy\Repository;

class TypeCriterion extends Exportable implements ScenarioCriterion
{
    public $typeList = [];

    private $repository;

    public function __construct(Repository $repository, array $typeList)
    {
        $this->typeList = $typeList;
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'type';
    }

    public function getDescription()
    {
        $descriptions = [];
        if (!empty($this->typeList)){
            foreach ($this->idList as $id){
                $descriptions[] = $this->repository->getPostTypeService()->loadPostType($id)->name;
            }
            if (!empty($descriptions)){
                $descriptions[0] = 'di tipo ' . $descriptions[0];
            }
        }

        return implode(', ', $descriptions);
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