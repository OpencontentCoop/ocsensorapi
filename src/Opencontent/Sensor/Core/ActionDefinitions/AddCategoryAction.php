<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Repository;
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

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'assign_to_operator';
        $parameter->isRequired = false;
        $parameter->type = 'int';
        $parameter->defaultValue = 0;
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'assign_to_group';
        $parameter->isRequired = false;
        $parameter->type = 'int';
        $parameter->defaultValue = 0;
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
        if ($repository->getSensorSettings()->get('UniqueCategoryCount')) {
            $categoryIdList = array(array_shift($categoryIdList));
        }

        $repository->getPostService()->setPostCategory($post, implode('-', $categoryIdList));
        $post = $repository->getPostService()->refreshPost($post);

        $this->fireEvent($repository, $post, $user, array('categories' => $categoryIdList));

        if ($action->getParameterValue('assign_to_operator') && $repository->getSensorSettings()->get('CategoryAutomaticAssign')) {
            $post = $repository->getPostService()->loadPost($post->id); //reload post
            $ownerIdList = array();
            foreach ($post->categories as $category)
                $ownerIdList = array_merge($ownerIdList, $category->operatorsIdList);

            if (!empty($ownerIdList)) {
                $action = new Action();
                $action->identifier = 'assign';
                $action->setParameter('participant_ids', $ownerIdList);
                $repository->getActionService()->runAction($action, $post);
            }
        }

        if ($action->getParameterValue('assign_to_group') && $repository->getSensorSettings()->get('CategoryAutomaticAssign')) {
            $post = $repository->getPostService()->loadPost($post->id); //reload post
            $approverIdList = array();
            foreach ($post->categories as $category)
                $approverIdList = array_merge($approverIdList, $category->groupsIdList);

            if (!empty($approverIdList)) {
                $action = new Action();
                $action->identifier = 'add_approver';
                $action->setParameter('participant_ids', $approverIdList);
                $repository->getActionService()->runAction($action, $post);
            }
        }
    }
}
