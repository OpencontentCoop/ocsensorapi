<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class SetExpiryAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'set_expiry';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_set_expiry' );
        $this->inputName = 'SetExpiry';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'expiry_days';
        $parameter->isRequired = true;
        $parameter->type = 'int';
        $parameter->inputName = 'SensorItemExpiry';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $expiryDays = intval( $action->getParameterValue( 'expiry_days' ) );
        if ( $expiryDays > 0 )
        {
            $repository->getPostService()->setPostExpirationInfo( $post, $expiryDays );
            $this->fireEvent( $repository, $post, $user, array( 'expiry' => $expiryDays ) );
        }
    }
}
