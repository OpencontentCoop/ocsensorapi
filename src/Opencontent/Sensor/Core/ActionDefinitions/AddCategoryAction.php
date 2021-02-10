<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
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
        $categoryNameList = [];
        foreach ($categoryIdList as $categoryId) {
            try {
                $category = $repository->getCategoryService()->loadCategory($categoryId);
                $categoryNameList[] = "#{$categoryId} ({$category->name})";
            } catch (NotFoundException $e) {
                throw new InvalidInputException("Item $categoryId is not a valid category");
            }
        }

        if ($repository->getSensorSettings()->get('UniqueCategoryCount')) {
            $categoryIdList = array(array_shift($categoryIdList));
        }

        $isChanged = true;
//        foreach ($post->categories as $category) {
//            if (in_array($category->id, $categoryIdList)) {
//                //$isChanged = false; //da questo dipende l'applicazione di eventuali scenari!
//                break;
//            }
//        }

        if ($isChanged) {
            $repository->getPostService()->setPostCategory($post, implode('-', $categoryIdList));

            $auditStruct = new AuditStruct();
            $auditStruct->createdDateTime = new \DateTime();
            $auditStruct->creator = $user;
            $auditStruct->post = $post;
            $auditStruct->text = "Impostata categoria " . implode(', ', $categoryNameList);
            $repository->getMessageService()->createAudit($auditStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('categories' => $categoryIdList));

        } else {
            $repository->getLogger()->notice('Category already set in post', array('categories' => $categoryIdList));
        }
    }
}
