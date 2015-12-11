<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;


class ModerateAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'moderate';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_moderate' );
        $this->inputName = 'Moderate';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'status';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $parameter->inputName = 'SensorItemModerationIdentifier';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $identifier = $action->getParameterValue( 'status' );
        $repository->getPostService()->setPostStatus( $post, 'moderation.' . $identifier );
        $repository->getPostService()->refreshPost( $post );
        $this->fireEvent( $repository, $post, $user );
    }
}