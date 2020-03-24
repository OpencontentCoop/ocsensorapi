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

        $currentApproverIds = $post->approvers->getParticipantIdList();

        $selectedParticipantIds = (array)$action->getParameterValue('participant_ids');
        if (!$allowMultipleApprover) {
            $selectedParticipantIds = [array_shift($selectedParticipantIds)];
        }

        if ($this->arrayIsEqual($selectedParticipantIds, $currentApproverIds)) {
            return;
        }

        $makeObserverIds = [];
        foreach ($currentApproverIds as $currentApproverId){
            if (!in_array($currentApproverId, $selectedParticipantIds)){
                $makeObserverIds[] = $currentApproverId;
            }
        }

        $repository->getLogger()->info('selected ' . implode(',', $selectedParticipantIds));
        $repository->getLogger()->info('current ' . implode(',', $currentApproverIds));
        $repository->getLogger()->info('make_observer ' . implode(',', $makeObserverIds));

        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleApprover = $roles->getParticipantRoleById(ParticipantRole::ROLE_APPROVER);
        $roleObserver = $roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER);

        foreach ($selectedParticipantIds as $id) {
            $repository->getParticipantService()->addPostParticipant($post, $id, $roleApprover);
            $isChanged = true;
        }

        foreach ($makeObserverIds as $id) {
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $id,
                $roleObserver
            );
            $isChanged = true;
        }

        if ($isChanged) {
            $post = $repository->getPostService()->refreshPost($post);
            $this->fireEvent($repository, $post, $user, array(
                'approvers' => $selectedParticipantIds,
                'observers' => $makeObserverIds,
            ));
        }
    }
}
