<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AddApproverAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'add_approver';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_add_approver');
        $this->inputName = 'Assign';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = true;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $isChanged = false;
        $allowMultipleApprover = $repository->getSensorSettings()->get('AllowMultipleApprover');

        $participantIds = (array)$action->getParameterValue('participant_ids');
        $currentApproverIds = $post->approvers->getParticipantIdList();
        $currentOwnerIds = $post->owners->getParticipantIdList();
        $makeApproverIds = array_diff($participantIds, $currentApproverIds);
        $makeObserverIds = array_diff($currentOwnerIds, $participantIds);

        if ($makeApproverIds == $currentOwnerIds && empty($makeApproverIds)) {
            return;
        }

        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleApprover = $roles->getParticipantRoleById(ParticipantRole::ROLE_APPROVER);
        $roleObserver = $roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER);

        if (!$allowMultipleApprover && count($makeApproverIds) > 1) {
            $makeApproverId = array_shift($makeApproverIds);
            $makeObserverIds = array_unique(array_merge($makeObserverIds, $makeApproverIds));
            $makeApproverIds = array($makeApproverId);
        }

        if (!$allowMultipleApprover){
            $makeObserverIds = array_unique(array_merge($makeObserverIds, $currentApproverIds));
        }

        foreach ($makeApproverIds as $id) {
            $repository->getParticipantService()->addPostParticipant($post, $id, $roleApprover);
            $isChanged = true;
        }

        if ($isChanged) {
            if (!$allowMultipleApprover) {
                foreach ($makeObserverIds as $id) {
                    $repository->getParticipantService()->addPostParticipant(
                        $post,
                        $id,
                        $roleObserver
                    );
                }
            }

            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array('approvers' => $makeApproverIds));
        }
    }
}
