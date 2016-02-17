<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddCategoryAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_category';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_add_category' );
        $this->inputName = 'AddCategory';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'category_id';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorItemCategory';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'assign_to_approver';
        $parameter->isRequired = false;
        $parameter->type = 'int';
        $parameter->defaultValue = 0;
        $parameter->inputName = 'SensorItemAssignToCategoryApprover';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $categoryIdList = $action->getParameterValue( 'category_id' );
        if ( $repository->getSensorSettings()->get( 'UniqueCategoryCount' ) )
        {
            $categoryIdList = array( array_shift( $categoryIdList ) );
        }

        $repository->getPostService()->setPostCategory( $post, implode( '-', $categoryIdList ) );
        $this->fireEvent( $repository, $post, $user, array( 'categories' => $categoryIdList ) );

        if ( $action->getParameterValue( 'assign_to_approver' ) && $repository->getSensorSettings()->get( 'CategoryAutomaticAssign' ) )
        {
            $post = $repository->getPostService()->loadPost( $post->id ); //reload post
            $ownerIdList = array();
            foreach( $post->categories as $category )
                $ownerIdList = array_merge( $ownerIdList, $category->userIdList );

            if ( !empty( $ownerIdList ) )
            {
                $action = new Action();
                $action->identifier = 'assign';
                $action->setParameter( 'participant_ids', $ownerIdList );
                $repository->getActionService()->runAction( $action, $post );
            }
        }
    }
}
