<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\Action\Action;
use Opencontent\Sensor\Api\ScenarioService as ScenarioServiceInterface;
use Opencontent\Sensor\Api\Values\Message\AuditStruct;
use Opencontent\Sensor\Api\Values\ParticipantRole;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\Scenarios\NullScenario;

abstract class ScenarioService implements ScenarioServiceInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    protected $roles;

    private static $avoidRecursion = [];

    protected $initScenarios;

    /**
     * @param Repository $repository
     * @params Scenario[] $initScenarios
     */
    public function __construct(Repository $repository, $initScenarios = null)
    {
        $this->repository = $repository;
        $this->roles = $this->repository->getParticipantService()->loadParticipantRoleCollection();
        if (!empty($initScenarios)) {
            $this->initScenarios = $initScenarios;
        }
    }

    public function applyScenario(Scenario $scenario, Post $post, $trigger, $context = [])
    {
        $scenario->setCurrentPost($post);
        $scenario->setCurrentContext($context);

        if ($this->match($scenario, $post) < 0){
            return false;
        }

        try {
            $this->repository->getLogger()->debug("Start apply scenario $scenario->id on post $post->id in trigger $trigger");

            if (isset(self::$avoidRecursion[$scenario->id][$post->id])){
                $this->repository->getLogger()->error("Avoid recursion applying scenario $scenario->id on post $post->id in trigger $trigger: abort the execution!");
                $auditStruct = new AuditStruct();
                $auditStruct->createdDateTime = new \DateTime();
                $auditStruct->creator = $this->repository->getUserService()->loadUser(\eZINI::instance()->variable("UserSettings", "UserCreatorID")); //@todo
                $auditStruct->post = $post;
                $auditStruct->text = "Error! A recursive execution detected: abort the scenario #{$scenario->id} execution!";
                $this->repository->getMessageService()->createAudit($auditStruct);
                $this->repository->getPostService()->refreshPost($post);

                return false;
            }
            self::$avoidRecursion[$scenario->id][$post->id] = true;

            if ($trigger === self::INIT_POST) {

                $this->setApprovers($post, $scenario->getApprovers());
                $this->setOwners($post, array_merge($scenario->getOwners(), $scenario->getOwnerGroups()));
                $this->setObservers($post, $scenario->getObservers());
                if ($post->reporter instanceof User
                    && $post->reporter->id != $post->author->id
                    && ($post->author->type == 'sensor_operator' || $this->repository->getSensorSettings()->get('AddBehalfOfUserAsObserver'))
                    && !in_array($post->reporter->id, $scenario->getApprovers())
                    && !in_array($post->reporter->id, $scenario->getOwners())
                    && !in_array($post->reporter->id, $scenario->getObservers())
                ) {
                    $this->repository->getParticipantService()->addPostParticipant(
                        $post,
                        $post->reporter->id,
                        $this->roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER)
                    );
                }

                if ($this->repository->getSensorSettings()->get('AddOperatorSuperUserAsObserver') && $post->author->type == 'sensor_operator'){
                    $this->repository->getParticipantService()->addPostParticipant(
                        $post,
                        $post->author->id,
                        $this->roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER)
                    );
                }

            } else {

                $auditStruct = new AuditStruct();
                $auditStruct->createdDateTime = new \DateTime();
                $auditStruct->creator = $this->repository->getUserService()->loadUser(\eZINI::instance()->variable("UserSettings", "UserCreatorID")); //@todo
                $auditStruct->post = $post;
                $auditStruct->text = $scenario->getApplicationMessage($trigger);
                $this->repository->getMessageService()->createAudit($auditStruct);

                if ($scenario->hasObservers()) {
                    $this->repository->getActionService()->runAction(
                        new Action('add_observer', ['participant_ids' => $scenario->getObservers()], true),
                        $post
                    );
                    $post = $this->repository->getPostService()->loadPost($post->id);
                }

                if ($scenario->hasOwners() || $scenario->hasOwnerGroups()) {
                    $this->repository->getActionService()->runAction(
                        new Action('assign', ['participant_ids' => $scenario->getOwners(), 'group_ids' => $scenario->getOwnerGroups()], true),
                        $post
                    );
                    $post = $this->repository->getPostService()->loadPost($post->id);
                }

                if ($scenario->hasApprovers()) {
                    $this->repository->getActionService()->runAction(
                        new Action('add_approver', ['participant_ids' => $scenario->getApprovers()], true),
                        $post
                    );
                    $post = $this->repository->getPostService()->loadPost($post->id);
                }

                if ($scenario->getExpiry() > 0){
                    $this->repository->getActionService()->runAction(
                        new Action('set_expiry', ['expiry_days' => $scenario->getExpiry()], true),
                        $post
                    );
                    $post = $this->repository->getPostService()->loadPost($post->id);
                }

                if ($scenario->hasCategory()){
                    $this->repository->getActionService()->runAction(
                        new Action('add_category', ['category_id' => [$scenario->getCategory()]], true),
                        $post
                    );
                    $post = $this->repository->getPostService()->loadPost($post->id);
                }
            }

            $this->repository->getLogger()->debug("End apply scenario $scenario->id on post $post->id in trigger $trigger");

            return true;

        }catch (\Exception $e){
            $this->repository->getLogger()->error($e->getMessage());

            return false;
        }
    }

    public function match(Scenario $scenario, Post $post)
    {
        if (empty($scenario->criteria) && !$scenario instanceof NullScenario){
            return 0;
        }

        $matches = 0;
        foreach ($scenario->criteria as $criterion){
            if (!$criterion->match($post)){
                return -1;
            }else{
                $matches++;
            }
        }

        return $matches;
    }

    private function setApprovers(Post $post, array $participants)
    {
        foreach ($participants as $participantId) {
            $this->repository->getParticipantService()->addPostParticipant(
                $post,
                $participantId,
                $this->roles->getParticipantRoleById(ParticipantRole::ROLE_APPROVER)
            );
        }
    }

    private function setOwners(Post $post, array $participants)
    {
        foreach ($participants as $participantId) {
            $this->repository->getParticipantService()->addPostParticipant(
                $post,
                $participantId,
                $this->roles->getParticipantRoleById(ParticipantRole::ROLE_OWNER)
            );
        }
    }

    private function setObservers(Post $post, array $participants)
    {
        foreach ($participants as $participantId) {
            $this->repository->getParticipantService()->addPostParticipant(
                $post,
                $participantId,
                $this->roles->getParticipantRoleById(ParticipantRole::ROLE_OBSERVER)
            );
        }
    }

}
