<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class RemoveObserverAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'remove_observer';
        $this->permissionDefinitionIdentifiers = array('can_remove_observer');
        $this->inputName = 'RemoveObserver';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_id';
        $parameter->isRequired = true;
        $parameter->type = 'integer';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $participantId = (integer)$action->getParameterValue('participant_id');
        $observer = $post->observers->getById($participantId);
        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleStandard = $roles->getParticipantRoleById(ParticipantRole::ROLE_STANDARD);
        if ($observer instanceof Participant){
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $observer->id,
                $roleStandard
            );

            $auditStruct = new AuditStruct();
            $auditStruct->createdDateTime = new \DateTime();
            $auditStruct->creator = $user;
            $auditStruct->post = $post;
            $auditStruct->text = "Rimosso osservatore #{$observer->id} ({$observer->name})";
            $repository->getMessageService()->createAudit($auditStruct);

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user);
        }
    }
}