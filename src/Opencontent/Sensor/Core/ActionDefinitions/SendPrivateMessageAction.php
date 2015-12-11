<?php

namespace OpenContent\Sensor\Core\ActionDefinitions;

use OpenContent\Sensor\Api\Action\Action;
use OpenContent\Sensor\Api\Action\ActionDefinition;
use OpenContent\Sensor\Api\Action\ActionDefinitionParameter;
use OpenContent\Sensor\Api\Repository;
use OpenContent\Sensor\Api\Values\Message\CommentStruct;
use OpenContent\Sensor\Api\Values\Message\PrivateMessageStruct;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;

class SendPrivateMessageAction extends ActionDefinition
{

    public function __construct()
    {
        $this->identifier = 'send_private_message';
        $this->permissionDefinitionIdentifiers = array( 'can_read', 'can_send_private_message' );
        $this->inputName = 'PrivateMessage';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $parameter->inputName = 'SensorItemPrivateMessage';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $parameter->inputName = 'SensorItemPrivateMessageReceiver';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run( Repository $repository, Action $action, Post $post, User $user )
    {
        $text = $action->getParameterValue( 'text' );
        $receiverIdList = $action->getParameterValue( 'participant_ids' );

        $messageStruct = new PrivateMessageStruct();
        $messageStruct->createdDateTime = new \DateTime();
        $messageStruct->creator = $repository->getCurrentUser();
        $messageStruct->post = $post;
        $messageStruct->text = $text;
        $messageStruct->receiverIdList = $receiverIdList;

        $repository->getMessageService()->createPrivateMessage( $messageStruct );
        $repository->getPostService()->refreshPost( $post );
        $this->fireEvent( $repository, $post, $user, array( 'text' => $text, 'receiver_ids' => $participantIdList ) );
    }
}
