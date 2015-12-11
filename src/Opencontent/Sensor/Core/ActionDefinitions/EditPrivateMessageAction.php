<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Message\PrivateMessageStruct;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class EditPrivateMessageAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'edit_message';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_send_private_message' );
        $this->inputName = 'EditMessage';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'id_text';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorEditMessage';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $idTextArray = $action->getParameterValue( 'id_text' );
        foreach( $idTextArray as $id => $text )
        {
            $messageStruct = new PrivateMessageStruct();
            $messageStruct->id = $id;
            $messageStruct->text = $text;
            $messageStruct->creator = $user;
            if ( $message = $repository->getMessageService()->updatePrivateMessage( $messageStruct ) )
            {
                $this->fireEvent( $repository, $post, $user, array( 'message' => $message ) );
            }
        }
        $repository->getPostService()->refreshPost( $post );
    }
}

