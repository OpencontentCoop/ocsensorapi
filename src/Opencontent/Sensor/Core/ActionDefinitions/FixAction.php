<?php

namespace Opencontent\Sensor\Core\ActionDefinitions;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\Action\ActionDefinition;
use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\PostService\ScenarioLoader;

class FixAction extends ActionDefinition
{
    public $identifier = 'fix';

    public $permissionDefinitionIdentifiers = array('can_read', 'can_fix');

    public $inputName = 'Fix';

    public function run(Repository $repository, Action $action, Post $post, User $user)
    {
        $roles = $repository->getParticipantService()->loadParticipantRoleCollection();
        $roleObserver = $roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER);
        $allowMultipleOwner = $repository->getSensorSettings()->get('AllowMultipleOwner');
        if ($post->owners->getUserById($user->id)) {
            $repository->getParticipantService()->addPostParticipant(
                $post,
                $post->owners->getParticipantByUserId($user->id)->id,
                $roleObserver
            );
            if (!$allowMultipleOwner){
                foreach ($post->owners as $owner) {
                    $repository->getParticipantService()->addPostParticipant(
                        $post,
                        $owner->id,
                        $roleObserver
                    );
                }
            }
        }
        $repository->getMessageService()->addTimelineItemByWorkflowStatus($post, Post\WorkflowStatus::FIXED);
        if ($repository->getParticipantService()->loadPostParticipantsByRole($post, ParticipantRole::ROLE_OWNER)->count() == 0) {
            $repository->getPostService()->setPostWorkflowStatus($post, Post\WorkflowStatus::FIXED);
        }
        $post = $repository->getPostService()->refreshPost($post);
        $this->fireEvent($repository, $post, $user);

        if ($repository->getSensorSettings()->get('ForceUrpApproverOnFix')) {            
            $scenarioLoader = new ScenarioLoader($repository, $post, $user);
            $scenario = $scenarioLoader->getScenario();            
            $approverIdList = $scenario->getApprovers();            
            $currentApproverIds = $post->approvers->getParticipantIdList();
            if (!$this->arrayIsEqual($currentApproverIds, $approverIdList)){
                $action = new Action();
                $action->identifier = 'add_approver';
                $action->setParameter('participant_ids', $approverIdList);
                $repository->getActionService()->runAction($action, $post);
                $post = $repository->getPostService()->loadPost($post->id);
                $repository->getLogger()->info('ForceUrpApproverOnFix is enabled: reset default approvers ' . implode(', ', $approverIdList));
            }
        }
    }
}
