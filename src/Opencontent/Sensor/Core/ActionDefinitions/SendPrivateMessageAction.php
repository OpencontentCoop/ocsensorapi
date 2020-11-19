<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\CommentStruct;
use Opencontent\Sensor\Api\Values\Message\PrivateMessageStruct;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class SendPrivateMessageAction extends ActionDefinition
{

    public function __construct()
    {
        $this->identifier = 'send_private_message';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_send_private_message');
        $this->inputName = 'PrivateMessage';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'text';
        $parameter->isRequired = true;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = false;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $text = $action->getParameterValue('text');
        $receiverIdList = (array)$action->getParameterValue('participant_ids');

        $participantIdList = $post->participants->getParticipantIdList();
        $diff = array_diff($receiverIdList, $participantIdList);
        if (!empty($diff)) {
            throw new InvalidInputException("Participant list contains invalid items");
        }

        foreach ($receiverIdList as $receiverId) {
            if ($post->participants->getParticipantById($receiverId)->roleIdentifier == ParticipantRole::ROLE_AUTHOR) {
                throw new InvalidInputException("Can not send private message to a user which is not an operator");
            }
        }

        $messageStruct = new PrivateMessageStruct();
        $messageStruct->createdDateTime = new \DateTime();
        $messageStruct->creator = $repository->getCurrentUser();
        $messageStruct->post = $post;
        $messageStruct->text = $text;
        $messageStruct->receiverIdList = $receiverIdList;

        $repository->getMessageService()->createPrivateMessage($messageStruct);
        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, array('text' => $text, 'receiver_ids' => $receiverIdList));
    }
}
