<?php

namespace Opencontent\Sensor\Legacy\PostService\Scenarios;

use Opencontent\Sensor\Legacy\PostService\ScenarioInterface;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Repository;
use eZContentObject;

class AreaApproverScenario implements ScenarioInterface
{
    /**
     * @var Repository
     */
    private $repository;

    private $approvers = [];

    private $owners = [];

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function match(Post $post, User $user)
    {
        if (count($post->areas) > 0) {
            $this->owners = [];

            foreach ($post->areas as $areaId) {
                $area = $this->repository->getAreaService()->loadArea($areaId);
                $this->owners = array_merge(
                    $this->owners,
                    explode('-', $area->operatorsIdList)
                );
            }

            $firstAreaScenario = new FirstAreaApproverScenario($this->repository);
            $this->approvers = $firstAreaScenario->getApprovers();

            return true;
        }

        return false;
    }

    public function getApprovers()
    {
        return $this->approvers;
    }

    public function getOwners()
    {
        return $this->owners;
    }

    public function getObservers()
    {
        return [];
    }


}