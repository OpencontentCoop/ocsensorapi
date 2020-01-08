<?php

namespace Opencontent\Sensor\Legacy\PostService\Scenarios;

use Opencontent\Sensor\Legacy\PostService\ScenarioInterface;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Repository;
use eZContentObject;

class FirstAreaApproverScenario implements ScenarioInterface
{
    /**
     * @var Repository
     */
    private $repository;

    private $approvers;

    public function __construct($repository)
    {
        $this->repository = $repository;
        if ($this->repository->getAreasTree() instanceof \eZContentObjectTreeNode) {
            $areas = $this->repository->getAreasTree()->attribute('children');
            if (count($areas)) {
                $firstAreaId = $areas[0]->attribute('id');
                $firstAreaObject = eZContentObject::fetch((int)$firstAreaId);
                if ($firstAreaObject instanceof eZContentObject) {
                    $dataMap = $firstAreaObject->dataMap();
                    if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
                        $this->approvers = explode('-', $dataMap['approver']->toString());
                    }
                }
            }
        }
    }

    public function match(Post $post, User $user)
    {
        return count($this->approvers) > 0;
    }

    public function getApprovers()
    {
        return $this->approvers;
    }

    public function getOwners()
    {
        return [];
    }

    public function getObservers()
    {
        return [];
    }


}