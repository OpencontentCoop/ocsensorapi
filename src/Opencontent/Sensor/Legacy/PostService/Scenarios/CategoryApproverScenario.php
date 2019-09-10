<?php

namespace Opencontent\Sensor\Legacy\PostService\Scenarios;

use Opencontent\Sensor\Legacy\PostService\ScenarioInterface;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Repository;
use eZContentObject;

class CategoryApproverScenario implements ScenarioInterface
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
        if (count($post->categories) > 0) {
            $this->approvers = [];

            foreach ($post->categories as $categoryId) {
                $category = $this->repository->getCategoryService()->loadCategory($categoryId);
                $this->approvers = array_merge(
                    $this->approvers,
                    explode('-', $category->groupsIdList)
                );
                $this->owners = array_merge(
                    $this->owners,
                    explode('-', $category->operatorsIdList)
                );
            }

            $firstAreaScenario = new FirstAreaApproverScenario($this->repository);
            $this->approvers = array_merge($firstAreaScenario->getApprovers(), $this->approvers);

            $this->approvers = array_unique($this->approvers);

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