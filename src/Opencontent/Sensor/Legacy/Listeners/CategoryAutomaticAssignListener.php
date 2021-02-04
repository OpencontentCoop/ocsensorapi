<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\SearchService;

class CategoryAutomaticAssignListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        return false;
        if ($param instanceof SensorEvent) {
            $user = $param->user;
            $post = $param->post;

            $approverIdList = array();
            foreach ($post->categories as $category) {
                $approverIdList = array_merge($approverIdList, $category->groupsIdList);
            }

            $ownerGroupsIdList = array();
            foreach ($post->categories as $category) {
                $ownerGroupsIdList = array_merge($ownerGroupsIdList, $category->ownerGroupsIdList);
            }

            $ownerIdList = array();
            foreach ($post->categories as $category) {
                $ownerIdList = array_merge($ownerIdList, $category->ownersIdList);
            }

            $observerIdList = array();
            foreach ($post->categories as $category) {
                $observerIdList = array_merge($observerIdList, $category->observersIdList);
            }
            foreach ($post->areas as $area) {
                $observerIdList = array_merge($observerIdList, $area->observersIdList);
            }

            if (!empty($observerIdList)) {
                $this->repository->getLogger()->debug('Add observer by category and area', ['observers' => $observerIdList]);
                $this->repository->getActionService()->runAction(
                    new Action('add_observer', ['participant_ids' => $observerIdList]),
                    $post
                );
                $post = $this->repository->getPostService()->loadPost($post->id);
            }

            if (empty($ownerIdList)
                && empty($ownerGroupsIdList)
                && !empty($approverIdList)
                && $this->repository->getSensorSettings()->get('CategoryAutomaticAssignToRandomOperator')
            ) {
                $luckyOwnerId = $this->getRandomOperatorFromGroups($approverIdList);
                $this->repository->getLogger()->debug('Find random operator by approver', ['approvers' => $approverIdList, 'random' => $luckyOwnerId]);
                if ($luckyOwnerId){
                    $ownerIdList = [$luckyOwnerId];
                }
            }

            if (!empty($ownerIdList) || !empty($ownerGroupsIdList)) {
                if (empty($ownerIdList)) {
                    $luckyOwnerId = $this->getRandomOperatorFromGroups($ownerGroupsIdList);
                    $this->repository->getLogger()->debug('Find random operator by groups', ['groups' => $ownerGroupsIdList, 'random' => $luckyOwnerId]);
                    if ($luckyOwnerId){
                        $ownerIdList = [$luckyOwnerId];
                    }
                }
                $this->repository->getLogger()->debug('Add owners by category', ['owners' => $ownerIdList, 'owner_groups' => $ownerGroupsIdList]);
                $this->repository->getActionService()->runAction(
                    new Action('assign', ['participant_ids' => $ownerIdList, 'group_ids' => $ownerGroupsIdList]),
                    $post
                );
                $post = $this->repository->getPostService()->loadPost($post->id);
            }

            if (!empty($approverIdList)) {
                $this->repository->getLogger()->debug('Add approver by category', ['approvers' => $observerIdList]);
                $this->repository->getActionService()->runAction(
                    new Action('add_approver', ['participant_ids' => $approverIdList]),
                    $post
                );
                $post = $this->repository->getPostService()->loadPost($post->id);
            }

            $this->repository->getUserService()->setLastAccessDateTime($user, $post);
        }
    }

    private function getRandomOperatorFromGroups($ownerGroupsIdList)
    {
        if ($this->repository->getSensorSettings()->get('CategoryAutomaticAssignToRandomOperator')) {
            foreach ($ownerGroupsIdList as $ownerGroupsId){
                $group = $this->repository->getGroupService()->loadGroup($ownerGroupsId);
                if ($group instanceof Group){
                    $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*');
                    $operators = $operatorResult['items'];
                    $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
                }
            }

            if (!empty($operators)) {
                /** @var Operator $luckyUser */
                $luckyUser = $operators[array_rand($operators, 1)];
                $this->repository->getLogger()->warning('Select lucky operator as owner: ' . $luckyUser->name . ' (' . $luckyUser->id . ')');
                return $luckyUser->id;
            }
        }

        return false;
    }

    private function recursiveLoadOperatorsByGroup(Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next']);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
        }

        return $operators;
    }
}