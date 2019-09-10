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

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function match(Post $post, User $user)
    {
        return true;
    }

    public function getApprovers()
    {
        $areas = $this->repository->getAreasTree()->attribute('children');
        if (count($areas)) {
            $firstAreaId = $areas[0]->attribute('id');
            $firstAreaObject = eZContentObject::fetch((int)$firstAreaId);
            if ($firstAreaObject instanceof eZContentObject) {
                $dataMap = $firstAreaObject->dataMap();
                if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
                    return explode('-', $dataMap['approver']->toString());
                }
            }
        }

        return [];
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