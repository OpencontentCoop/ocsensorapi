<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Utils\TimelineTools;

class CloseAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'close';
        $this->permissionDefinitionIdentifiers = ['can_read', 'can_close'];
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
                $repository->getParticipantService()->loadParticipantRoleCollection()->getParticipantRoleById(
                    ParticipantRole::ROLE_OBSERVER
                )
            );
        }

        $label = $action->hasParameter('label') ? $action->getParameterValue('label') : 'sensor.close';
        $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::CLOSED, $label);
        if (!$post->workflowStatus->is(Post\WorkflowStatus::CLOSED)) {
            $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::CLOSED);
        }

        $auditStruct = new AuditStruct();
        $auditStruct->createdDateTime = new \DateTime();
        $auditStruct->creator = $user;
        $auditStruct->post = $post;
        $auditStruct->text = "Impostato stato a " . $repository->getPostStatusService()->loadPostStatus(str_replace('sensor.', '', $label))->name;
        $repository->getMessageService()->createAudit($auditStruct);

        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user, ['label' => $label]);
    }
}
