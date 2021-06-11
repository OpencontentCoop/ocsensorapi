<?php

namespace Opencontent\Sensor\Legacy\Scenarios\Criteria;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;
use Opencontent\Sensor\Legacy\Repository;

class CategoryCriterion extends Exportable implements ScenarioCriterion
{
    public $idList = [];

    private $repository;

    public function __construct(Repository $repository, array $idList)
    {
        $this->idList = $idList;
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'category';
    }

    public function getDescription()
    {
        $descriptions = [];
        if (!empty($this->idList)){
            foreach ($this->idList as $id){
                try {
                    $descriptions[] = $this->repository->getCategoryService()->loadCategory($id)->name;
                }catch (\Exception $e){

                }
            }
            if (!empty($descriptions)){
                $descriptions[0] = 'categorizzata con ' . $descriptions[0];
            }
        }

        return implode(', ', $descriptions);
    }

    public function match(Post $post)
    {
        if (empty($this->idList)){
            return true;
        }

        foreach ($post->categories as $category){
            if (in_array($category->id, $this->idList)){
                return true;
            }
        }

        return false;
    }

    public function jsonSerialize()
    {
        return $this->idList;
    }
}