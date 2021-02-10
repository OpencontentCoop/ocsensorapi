<?php

namespace Opencontent\Sensor\Legacy\Scenarios;

use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\SearchService;
use eZContentObject;

class SensorScenario extends Scenario
{
    private $repository;

    private $useRandomOwner = false;

    private $makeReporterAsApprover = false;

    private $makeReporterAsOwner = false;

    private $makeReporterAsObserver = false;

    public function __construct(Repository $repository, eZContentObject $object)
    {
        $this->repository = $repository;

        $this->id = $object->attribute('id');
        $dataMap = $object->dataMap();

        $this->triggers = explode('|', $dataMap['triggers']->toString());

        if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
            $this->approversIdList = array_map( 'intval', explode('-', $dataMap['approver']->toString()));
        }
        if (isset($dataMap['owner_group']) && $dataMap['owner_group']->hasContent()) {
            $this->ownerGroupsIdList = array_map( 'intval', explode('-', $dataMap['owner_group']->toString()));
        }
        if (isset($dataMap['owner']) && $dataMap['owner']->hasContent()) {
            $this->ownersIdList = array_map( 'intval', explode('-', $dataMap['owner']->toString()));
        }
        if (isset($dataMap['observer']) && $dataMap['observer']->hasContent()) {
            $this->observersIdList = array_map( 'intval', explode('-', $dataMap['observer']->toString()));
        }

        if (isset($dataMap['criterion_type']) && $dataMap['criterion_type']->hasContent()) {
            $this->criteria[] = new Criteria\TypeCriterion(
                $this->repository,
                explode('|', $dataMap['criterion_type']->toString())
            );
        }
        if (isset($dataMap['criterion_category']) && $dataMap['criterion_category']->hasContent()) {
            $this->criteria[] = new Criteria\CategoryCriterion(
                $this->repository,
                array_map( 'intval', explode('-', $dataMap['criterion_category']->toString()))
            );
        }
        if (isset($dataMap['criterion_area']) && $dataMap['criterion_area']->hasContent()) {
            $this->criteria[] = new Criteria\AreaCriterion(
                $this->repository,
                array_map( 'intval', explode('-', $dataMap['criterion_area']->toString()))
            );
        }
        if (isset($dataMap['criterion_reporter_group']) && $dataMap['criterion_reporter_group']->hasContent()) {
            $this->criteria[] = new Criteria\ReporterGroupCriterion(
                $this->repository,
                array_map( 'intval', explode('-', $dataMap['criterion_reporter_group']->toString()))
            );
        }

        if (isset($dataMap['random_owner'])) {
            $this->useRandomOwner = $dataMap['random_owner']->attribute('data_int') == 1;
        }
        if (isset($dataMap['reporter_as_approver'])) {
            $this->makeReporterAsApprover = $dataMap['reporter_as_approver']->attribute('data_int') == 1;
        }
        if (isset($dataMap['reporter_as_owner'])) {
            $this->makeReporterAsOwner = $dataMap['reporter_as_owner']->attribute('data_int') == 1;
        }
        if (isset($dataMap['reporter_as_observer'])) {
            $this->makeReporterAsObserver = $dataMap['reporter_as_observer']->attribute('data_int') == 1;
        }
    }

    public function getApprovers()
    {
        if (
            $this->makeReporterAsApprover
            && $this->currentPost instanceof Post
            && $this->currentPost->author->id !== $this->currentPost->reporter->id
        ){
            return [$this->currentPost->reporter->id];
        }

        return parent::getApprovers();
    }

    public function getOwners()
    {
        if (empty($this->ownersIdList)){
            if (
                $this->makeReporterAsOwner
                && $this->currentPost instanceof Post
                && $this->currentPost->author->id !== $this->currentPost->reporter->id
            ){
                return [$this->currentPost->reporter->id];

            }elseif ($this->useRandomOwner && $this->currentPost instanceof Post) {
                if (!empty($this->approversIdList) && empty($this->ownerGroupsIdList)) {
                    $lucky = $this->getRandomOperatorFromGroups($this->approversIdList);
                    if ($lucky) {
                        return [$lucky];
                    }
                } elseif (!empty($this->ownerGroupsIdList)) {
                    $lucky = $this->getRandomOperatorFromGroups($this->ownerGroupsIdList);
                    if ($lucky) {
                        return [$lucky];
                    }
                }
            }
        }

        return parent::getOwners();
    }

    public function getObservers()
    {
        $observers = parent::getObservers();
        if (
            $this->makeReporterAsObserver
            && $this->currentPost instanceof Post
            && $this->currentPost->author->id !== $this->currentPost->reporter->id
        ){
            $observers[] = $this->currentPost->reporter->id;
        }

        return $observers;
    }

    private function getRandomOperatorFromGroups($ownerGroupsIdList)
    {
        foreach ($ownerGroupsIdList as $ownerGroupsId){
            $group = $this->repository->getGroupService()->loadGroup($ownerGroupsId);
            if ($group instanceof Group){
                $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*');
                $operators = $operatorResult['items'];
                $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
            }
        }

        if (!empty($operators)) {
            /** @var Operator $luckyUser */
            $luckyUser = $operators[array_rand($operators, 1)];
            $this->repository->getLogger()->warning('Select lucky operator as owner: ' . $luckyUser->name . ' (' . $luckyUser->id . ')');
            return $luckyUser->id;
        }

        return false;
    }

    private function recursiveLoadOperatorsByGroup(Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next']);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
        }

        return $operators;
    }

    public static function generateRemoteId(array $stringAttributes)
    {
        $triggers = explode('|', $stringAttributes['triggers']);
        sort($triggers);

        $typeCriteria = [];
        if (isset($stringAttributes['criterion_type']) && !empty($stringAttributes['criterion_type'])) {
            $typeCriteria = explode('|', $stringAttributes['criterion_type']);
            sort($typeCriteria);
        }

        $categoryCriteria = [];
        if (isset($stringAttributes['criterion_category']) && !empty($stringAttributes['criterion_category'])) {
            $categoryCriteria = array_map( 'intval', explode('-', $stringAttributes['criterion_category']));
            sort($categoryCriteria);
        }

        $areaCriteria = [];
        if (isset($stringAttributes['criterion_area']) && !empty($stringAttributes['criterion_area'])) {
            $areaCriteria = array_map( 'intval', explode('-', $stringAttributes['criterion_area']));
            sort($areaCriteria);
        }

        $reporterGroupCriteria = [];
        if (isset($stringAttributes['criterion_reporter_group']) && !empty($stringAttributes['criterion_reporter_group'])) {
            $reporterGroupCriteria = array_map( 'intval', explode('-', $stringAttributes['criterion_reporter_group']));
            sort($reporterGroupCriteria);
        }

        $strings = [];
        $strings[] = implode('.', $triggers);
        $strings[] = 'type_' . implode('.', $typeCriteria);
        $strings[] = 'category_' . implode('.', $categoryCriteria);
        $strings[] = 'area_' . implode('.', $areaCriteria);
        $strings[] = 'reportergroup_' . implode('.', $reporterGroupCriteria);

        return 'scenario_' . md5(implode('_', $strings));
    }

    public function jsonSerialize()
    {
        $data = parent::jsonSerialize();
        foreach ($data['assignments'] as $role => $idList){
            $users = [];
            foreach ($idList as $id){
                $user = $this->repository->getUserService()->loadUser($id);
                //$user = $this->repository->getOperatorService()->loadOperator($id, []);
                if (!$user->email){
                    $user = $this->repository->getGroupService()->loadGroup($id, []);
                }
                $users[] = $user;
            }
            $data['assignments'][$role] =  $users;
        }
        $data['assignments']['reporter_as_approver'] = $this->makeReporterAsApprover;
        $data['assignments']['reporter_as_owner'] = $this->makeReporterAsOwner;
        $data['assignments']['reporter_as_observer'] = $this->makeReporterAsObserver;
        $data['assignments']['random_owner'] = $this->useRandomOwner;

        return $data;
    }

    public static function getAvailableEvents()
    {
        return [
            'on_create' => 'Creazione della segnalazione',
            'on_add_category' => 'Assegnazione di categoria alla segnalazione',
            'on_add_area' => 'Assegnazione di zona alla segnalazione',
            'on_fix' => 'Fine lavorazione della segnalazione',
            'on_close' => 'Chiusura della segnalazione',
        ];
    }

    /**
     * @param string $trigger
     * @return string
     */
    public function getApplicationMessage($trigger)
    {
        $currentPost = $this->currentPost;

        $this->currentPost = null;

        $data = $this->jsonSerialize();
        $assignments = $data['assignments'];
        $details = [];
        foreach ($assignments as $role => $users){
            $roleName = false;
            if ($role == 'approver'){
                $roleName = 'Riferimento';
            }elseif ($role == 'owner_group'){
                $roleName = 'Gruppo incaricato';
            }elseif ($role == 'owner'){
                $roleName = 'Incaricato';
            }elseif ($role == 'observer'){
                $roleName = 'Osservatore';
            }

            if ($roleName){
                $nameList = [];
                foreach ($users as $user){
                    $nameList[] = $user->name;
                }
                if ($role == 'approver' && $assignments['reporter_as_approver']){
                    $nameList[] = 'Operatore segnalatore';
                }elseif ($role == 'owner'){
                    if ($assignments['reporter_as_owner']){
                        $nameList[] = 'Operatore segnalatore';
                    }
                    if ($assignments['random_owner']){
                        $nameList[] = 'Operatore casuale';
                    }
                }elseif ($role == 'observer' && $assignments['reporter_as_observer']){
                    $nameList[] = 'Operatore segnalatore';
                }
                if (!empty($nameList)) {
                    $details[] = ' • ' . $roleName . ': ' . implode(', ', $nameList);
                }
            }
        }
        $availableEvents = self::getAvailableEvents();
        $eventMessage = strtolower($availableEvents[$trigger]);

        $criteriaMessages = [];
        foreach ($this->criteria as $criterion){
            $criteriaMessages[] = $criterion->getDescription();
        }
        $criteriaMessage = implode(' e ', $criteriaMessages);

        $this->currentPost = $currentPost;

        return "In seguito alla {$eventMessage} {$criteriaMessage}, viene eseguita l'assegnazione automatica #{$this->id}: " . implode(" ", $details);
    }
}