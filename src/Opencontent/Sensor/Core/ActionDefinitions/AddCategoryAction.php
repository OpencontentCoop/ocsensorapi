<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Participant\ApproverCollection;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\SearchService;

class AddCategoryAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_category';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_add_category');
        $this->inputName = 'AddCategory';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'category_id';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {

        $categoryIdList = (array)$action->getParameterValue('category_id');
        foreach ($categoryIdList as $categoryId) {
            try {
                $repository->getCategoryService()->loadCategory($categoryId);
            } catch (NotFoundException $e) {
                throw new InvalidInputException("Item $categoryId is not a valid category");
            }
        }

        $isChanged = true;
        if ($repository->getSensorSettings()->get('UniqueCategoryCount')) {
            $categoryIdList = array(array_shift($categoryIdList));

            foreach ($post->categories as $category) {
                if (in_array($category->id, $categoryIdList)) {
                    //$isChanged = false;
                    break;
                }
            }
        }

        if ($isChanged) {
            $repository->getPostService()->setPostCategory($post, implode('-', $categoryIdList));
            $post = $repository->getPostService()->refreshPost($post);

            $this->fireEvent($repository, $post, $user, array('categories' => $categoryIdList));

            if ($repository->getSensorSettings()->get('CategoryAutomaticAssign')) {

                $post = $repository->getPostService()->loadPost($post->id); //reload post

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
                    $repository->getLogger()->debug('Add observer by category', ['observers' => $observerIdList]);
                    $action = new Action();
                    $action->identifier = 'add_observer';
                    $action->setParameter('participant_ids', $observerIdList);
                    $repository->getActionService()->runAction($action, $post);
                    $post = $repository->getPostService()->loadPost($post->id);
                }

                if (empty($ownerIdList)
                    && empty($ownerGroupsIdList)
                    && !empty($approverIdList)
                    && $repository->getSensorSettings()->get('CategoryAutomaticAssignToRandomOperator')
                ) {
                    $luckyOwnerId = $this->getRandomOperatorFromGroups($repository, $approverIdList);
                    $repository->getLogger()->debug('Find random operator by approver', ['approvers' => $approverIdList, 'random' => $luckyOwnerId]);
                    if ($luckyOwnerId){
                        $ownerIdList = [$luckyOwnerId];
                    }
                }

                if (!empty($ownerIdList) || !empty($ownerGroupsIdList)) {
                    if (empty($ownerIdList)) {
                        $luckyOwnerId = $this->getRandomOperatorFromGroups($repository, $ownerGroupsIdList);
                        if ($luckyOwnerId){
                            $ownerIdList = [$luckyOwnerId];
                        }
                    }
                    $repository->getLogger()->debug('Add owners by category', ['owners' => $ownerIdList, 'owner_groups' => $ownerGroupsIdList]);
                    $action = new Action();
                    $action->identifier = 'assign';
                    $action->setParameter('participant_ids', $ownerIdList);
                    $action->setParameter('group_ids', $ownerGroupsIdList);
                    $repository->getActionService()->runAction($action, $post);
                    $post = $repository->getPostService()->loadPost($post->id);
                }

                if (!empty($approverIdList)) {
                    $repository->getLogger()->debug('Add approver by category', ['approvers' => $observerIdList]);
                    $action = new Action();
                    $action->identifier = 'add_approver';
                    $action->setParameter('participant_ids', $approverIdList);
                    $repository->getActionService()->runAction($action, $post);
                    $post = $repository->getPostService()->loadPost($post->id);
                }

                $repository->getUserService()->setLastAccessDateTime($user, $post);
            }
        } else {
            $repository->getLogger()->notice('Category already set in post', array('categories' => $categoryIdList));
        }
    }

    private function getRandomOperatorFromGroups(Repository $repository, $ownerGroupsIdList)
    {
        if ($repository->getSensorSettings()->get('CategoryAutomaticAssignToRandomOperator')) {
            foreach ($ownerGroupsIdList as $ownerGroupsId){
                $group = $repository->getGroupService()->loadGroup($ownerGroupsId);
                if ($group instanceof Group){
                    $operatorResult = $repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*');
                    $operators = $operatorResult['items'];
                    $this->recursiveLoadOperatorsByGroup($repository, $group, $operatorResult, $operators);
                }
            }

            if (!empty($operators)) {
                /** @var Operator $luckyUser */
                $luckyUser = $operators[array_rand($operators, 1)];
                $repository->getLogger()->warning('Select lucky operator as owner: ' . $luckyUser->name . ' (' . $luckyUser->id . ')');
                return $luckyUser->id;
            }
        }

        return false;
    }

    private function recursiveLoadOperatorsByGroup(Repository $repository, Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next']);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($repository, $group, $operatorResult, $operators);
        }

        return $operators;
    }
}
