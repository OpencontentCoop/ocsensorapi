<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddAreaAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_area';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_add_area' );
        $this->inputName = 'AddArea';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'area_id';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorItemArea';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $areaIdList = $action->getParameterValue( 'area_id' );

        $repository->getPostService()->setPostArea( $post, implode( '-', $areaIdList ) );
        $this->fireEvent( $repository, $post, $user, array( 'areas' => $areaIdList ) );
    }
}
