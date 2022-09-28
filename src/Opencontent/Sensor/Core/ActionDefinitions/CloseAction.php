<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CloseAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'close';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_close');
        $this->inputName = 'Close';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'label';
        $parameter->isRequired = false;
        $parameter->type = 'string';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        foreach ($post->owners as $owner) {
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $owner->id,
                $repository->getParticipantService()->loadParticipantRoleCollection()->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER)
            );
        }

        $label = $action->getParameterValue('label');
        $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::CLOSED, $label);
        $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::CLOSED);
        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, ['label' => $label]);
    }
}
