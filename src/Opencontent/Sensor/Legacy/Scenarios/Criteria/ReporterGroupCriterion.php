<?php

namespace Opencontent\Sensor\Legacy\Scenarios\Criteria;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;
use Opencontent\Sensor\Legacy\Repository;

class ReporterGroupCriterion extends Exportable implements ScenarioCriterion
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
        return 'reporter_group';
    }

    public function getDescription()
    {
        $descriptions = [];
        if (!empty($this->idList)){
            foreach ($this->idList as $id){
                try {
                    $descriptions[] = $this->repository->getGroupService()->loadGroup($id, [])->name;
                }catch (\Exception $e){

                }
            }
            if (!empty($descriptions)){
                $descriptions[0] = 'il cui reporter appartiene al gruppo ' . $descriptions[0];
            }
        }

        return implode(', ', $descriptions);
    }

    public function match(Post $post)
    {
        if (empty($this->idList)){
            return true;
        }

        $reporterGroups = $post->reporter->groups;
        foreach ($reporterGroups as $groupId){
            if (!in_array($groupId, $this->idList)){
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