<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Action\ActionDefinitionParameter;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class AssignAction extends ActionDefinition
{
    public function __construct()
    {
        $this->identifier = 'assign';
        $this->permissionDefinitionIdentifiers = array('can_read', 'can_assign');
        $this->inputName = 'Assign';

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'participant_ids';
        $parameter->isRequired = false;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;

        $parameter = new ActionDefinitionParameter();
        $parameter->identifier = 'group_ids';
        $parameter->isRequired = false;
        $parameter->type = 'array';
        $this->parameterDefinitions[] = $parameter;
    }

    protected function hasParameterDefinition($identifier)
    {
        foreach ($this->parameterDefinitions as $parameterDefinition){
            if ($parameterDefinition->identifier == $identifier){
                return true;
            }
        }

        return false;
    }

    protected function getParticipantChanges($parameter, $type, Repository $repository, Action $action, Post $post)
    {
        $makeOwnerIds = $makeObserverIds = [];
        $allowMultipleOwner = $repository->getSensorSettings()->get('AllowMultipleOwner');

        $participantIds = [];
        $requestParticipantIds = $this->hasParameterDefinition($parameter) && !empty($action->getParameterValue($parameter)) ? (array)$action->getParameterValue($parameter) : [];
        foreach ($requestParticipantIds as $participantId){
            if ($type == Participant::TYPE_USER) {
                if ($repository->getUserService()->loadUser($participantId)->isEnabled) {
                    $participantIds[] = $participantId;
                }
            }
            if ($type == Participant::TYPE_GROUP) {
                if ($repository->getGroupService()->loadGroup($participantId) instanceof Group) {
                    $participantIds[] = $participantId;
                }
            }
        }

        //if (!empty($participantIds) || $type == Participant::TYPE_USER) {
            $currentApproverIds = $post->approvers->getParticipantIdListByType($type);
            $currentOwnerIds = $post->owners->getParticipantIdListByType($type);
            $makeOwnerIds = array_diff($participantIds, $currentOwnerIds, $currentApproverIds);
            $makeObserverIds = array_diff($currentOwnerIds, $participantIds);

//            if ($makeObserverIds == $currentOwnerIds && empty($makeOwnerIds)) {
//                $repository->getLogger()->notice('Post is already assigned to ' . $type, array('make_owners' => $makeOwnerIds));
//            }
        //}

        if (!$allowMultipleOwner && count($makeOwnerIds) > 1) {
            $makeOwnerId = array_shift($makeOwnerIds);
            $makeObserverIds = array_unique(array_merge($makeObserverIds, $makeOwnerIds));
            $makeOwnerIds = array($makeOwnerId);
        }

        return [
            'owners' => $makeOwnerIds,
            'observers' => $makeObserverIds,
        ];
    }

    protected function checkParameters(Action $action)
    {
        if ($this->hasParameterDefinition('group_ids')){
            if (empty($action->getParameterValue('participant_ids')) && empty($action->getParameterValue('group_ids'))){
                throw new InvalidArgumentException("Parameter participant_ids or group_ids not found");
            }
        }
        return parent::checkParameters($action);
    }

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $userChanges = $this->getParticipantChanges('participant_ids', Participant::TYPE_USER, $repository, $action, $post);
        $repository->getLogger()->debug('Change users in assign', $userChanges);

        if ($this->hasParameterDefinition('group_ids')) {
            $groupChanges = $this->getParticipantChanges('group_ids', Participant::TYPE_GROUP, $repository, $action, $post);
            $repository->getLogger()->debug('Change groups in assign', $groupChanges);
        }else{
            $groupChanges = ['owners' => [], 'observers' => []];
        }

        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleOwner = $roles->getParticipantRoleById(ParticipantRole::ROLE_OWNER);
        $roleObserver = $roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER);

        $doRefresh = false;
        $owners = array_merge($userChanges['owners'], $groupChanges['owners']);
        foreach ($owners as $id) {
            $repository->getParticipantService()->addPostParticipant($post, $id, $roleOwner);
            $doRefresh = true;
        }

        $observers = array_merge($userChanges['observers'], $groupChanges['observers']);
        foreach ($observers as $id) {
            $repository->getParticipantService()->addPostParticipant($post, $id, $roleObserver);
            $doRefresh = true;
        }

        if (!empty($userChanges['owners'])) {
            $repository->getLogger()->debug('Found owner: make post assigned', ['post' => $post->id]);
            $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::ASSIGNED);
        }elseif (!(empty($userChanges['owners']) && empty($userChanges['observers'])) && $post->workflowStatus->code == Post\WorkflowStatus::ASSIGNED){
            $repository->getLogger()->debug('No owners found: make post read', ['post' => $post->id]);
            $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::READ);
            $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::READ);
        }

        if (!empty($owners)){
            //$repository->getLogger()->debug('Change workflow', $owners);
            $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::ASSIGNED, $owners);
        }

        if ($doRefresh) {
            $post = $repository->getPostService()->refreshPost($post);
        }

        if (!empty($userChanges['owners'])) {
            $this->fireEvent($repository, $post, $user, array('owners' => $userChanges['owners']));
        }
        if (!empty($groupChanges['owners']) && empty($userChanges['owners'])) {
            $this->fireEvent($repository, $post, $user, array('owner_groups' => $groupChanges['owners']), 'on_group_assign');
        }
    }
}
