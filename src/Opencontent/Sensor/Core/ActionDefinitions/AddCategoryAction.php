<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Participant\ApproverCollection;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

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
                    $action = new Action();
                    $action->identifier = 'add_observer';
                    $action->setParameter('participant_ids', $observerIdList);
                    $repository->getActionService()->runAction($action, $post);
                    $post = $repository->getPostService()->loadPost($post->id);
                }


                if (!empty($approverIdList)) {

                    //@todo check if group contains users

                    $action = new Action();
                    $action->identifier = 'add_approver';
                    $action->setParameter('participant_ids', $approverIdList);
                    $repository->getActionService()->runAction($action, $post);
                    $post = $repository->getPostService()->loadPost($post->id);

                    if (empty($ownerIdList)){
                        $ownerId = $this->getOperatorFromApprovers($repository, $post);
                    }else{
                        $ownerId = array_shift($ownerIdList);
                        $repository->getLogger()->info('Select category owner: ' . $ownerId);
                    }

                    if ($ownerId) {
                        $action = new Action();
                        $action->identifier = 'assign';
                        $action->setParameter('participant_ids', [$ownerId]);
                        // run action without check permission because current approver is now observer
                        $repository->getActionService()->loadActionDefinitionByIdentifier($action->identifier)->run(
                            $repository,
                            $action,
                            $post,
                            $repository->getCurrentUser()
                        );;
                    }
                }
                $repository->getUserService()->setLastAccessDateTime($user, $post);
            }
        }else{
            $repository->getLogger()->notice('Category already set in post', array('categories' => $categoryIdList));
        }
    }

    private function getOperatorFromApprovers(Repository $repository, Post $post)
    {
        $currentApprovers = [];
        /** @var Participant $approver */
        foreach ($post->approvers as $approver) {
            if ($approver->type = 'group') {
                $currentApprovers = array_merge($currentApprovers, $approver->users);
            }
        }

        if (!empty($currentApprovers)) {
            $luckyUser = $currentApprovers[array_rand($currentApprovers, 1)];
            $repository->getLogger()->warning('Select lucky operator as owner: ' . $luckyUser->name);
            return $luckyUser->id;
        }

        return false;
    }
}
