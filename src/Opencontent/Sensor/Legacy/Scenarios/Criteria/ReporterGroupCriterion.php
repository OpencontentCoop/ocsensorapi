<?php

namespace Opencontent\Sensor\Legacy\Scenarios\Criteria;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;

class ReporterGroupCriterion extends Exportable implements ScenarioCriterion
{
    public $idList = [];

    public function __construct(array $idList)
    {
        $this->idList = $idList;
    }

    public function getIdentifier()
    {
        return 'reporter_group';
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