<?php

namespace Opencontent\Sensor\Legacy\Scenarios;

use Opencontent\Sensor\Api\ScenarioService;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Repository;
use eZContentObject;

class FirstAreaApproverScenario extends Scenario
{
    public function __construct(Repository $repository)
    {
        $this->id = 1;
        
        $this->triggers = [ScenarioService::INIT_POST];

        $this->repository = $repository;
        if ($this->repository->getAreasRootNode() instanceof \eZContentObjectTreeNode) {
            $areas = $this->repository->getAreasTree()->attribute('children');
            if (count($areas)) {
                $firstAreaId = $areas[0]->attribute('id');
                $firstAreaObject = eZContentObject::fetch((int)$firstAreaId);
                if ($firstAreaObject instanceof eZContentObject) {
                    $dataMap = $firstAreaObject->dataMap();
                    if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
                        $approvers = explode('-', $dataMap['approver']->toString());
                        $this->approversIdList = array_map('intval', $approvers);
                    }
                }
            }
        }
    }
}