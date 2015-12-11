<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Message\ResponseStruct;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class AddResponseAction extends ActionDefinition
{

    public function __construct()
    {
        $this->identifier = 'add_response';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_respond' );
        $this->inputName = 'Respond';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $parameter->inputName = 'SensorItemResponse';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $text = $action->getParameterValue( 'text' );

        $responseStruct = new ResponseStruct();
        $responseStruct->createdDateTime = new \DateTime();
        $responseStruct->creator = $repository->getCurrentUser();
        $responseStruct->post = $post;
        $responseStruct->text = $text;

        $repository->getMessageService()->createResponse( $responseStruct );
        $repository->getPostService()->refreshPost( $post );
        $this->fireEvent( $repository, $post, $user, array( 'text' => $text ) );
    }
}
