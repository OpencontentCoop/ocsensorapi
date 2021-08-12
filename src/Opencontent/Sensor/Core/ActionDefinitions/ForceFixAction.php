<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Event;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class ForceFixAction extends ActionDefinition
{
    public $identifier = 'force_fix';

    public $permissionDefinitionIdentifiers = array('can_read', 'can_force_fix');

    public $inputName = 'ForceFix';

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleObserver = $roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER);
        foreach ($post->owners as $owner) {
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $owner->id,
                $roleObserver
            );
        }

        if (!$this->currentUserIsObserver($post, $user)){
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $user->id,
                $roleObserver
            );
        }

        $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::FIXED);
        $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::FIXED);

        $auditStruct = new AuditStruct();
        $auditStruct->createdDateTime = new \DateTime();
        $auditStruct->creator = $user;
        $auditStruct->post = $post;
        $auditStruct->text = "Forzato intervento terminato";
        $repository->getMessageService()->createAudit($auditStruct);

        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user);

        $event = new Event();
        $event->identifier = 'on_fix';
        $event->post = $post;
        $event->user = $user;
        $repository->getEventService()->fire($event);
    }

    private function currentUserIsObserver($post, $user)
    {
        $observers = $post->participants->getParticipantsByRole(ParticipantRole::ROLE_OBSERVER);
        foreach ($observers as $participant){
            if ($participant->id == $user->id){
                return true;
            }
        }

        return false;
    }
}
