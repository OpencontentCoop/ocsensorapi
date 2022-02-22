<?php

namespace Opencontent\Sensor\Legacy\Scenarios;

use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\SearchService;
use eZContentObject;
use Opencontent\Sensor\Legacy\Utils\Translator;

class SensorScenario extends Scenario
{
    private $repository;

    private $useRandomOwner = false;

    private $makeReporterAsApprover = false;

    private $makeReporterAsOwner = false;

    private $makeReporterAsObserver = false;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public static function fromContentObject(Repository $repository, eZContentObject $object)
    {
        $scenario = new SensorScenario($repository);
        $scenario->id = $object->attribute('id');
        $dataMap = $object->dataMap();

        if (isset($dataMap['triggers'])) {
            $scenario->triggers = explode('|', $dataMap['triggers']->toString());
        }

        if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
            $scenario->approversIdList = array_map( 'intval', explode('-', $dataMap['approver']->toString()));
        }
        if (isset($dataMap['owner_group']) && $dataMap['owner_group']->hasContent()) {
            $scenario->ownerGroupsIdList = array_map( 'intval', explode('-', $dataMap['owner_group']->toString()));
        }
        if (isset($dataMap['owner']) && $dataMap['owner']->hasContent()) {
            $scenario->ownersIdList = array_map( 'intval', explode('-', $dataMap['owner']->toString()));
        }
        if (isset($dataMap['observer']) && $dataMap['observer']->hasContent()) {
            $scenario->observersIdList = array_map('intval', explode('-', $dataMap['observer']->toString()));
        }
        if (isset($dataMap['category']) && $dataMap['category']->hasContent()) {
            $scenario->category = (int)$dataMap['category']->toString();
        }

        if (isset($dataMap['criterion_type']) && $dataMap['criterion_type']->hasContent()) {
            $scenario->criteria[] = new Criteria\TypeCriterion(
                $scenario->repository,
                explode('|', $dataMap['criterion_type']->toString())
            );
        }
        if (isset($dataMap['criterion_category']) && $dataMap['criterion_category']->hasContent()) {
            $scenario->criteria[] = new Criteria\CategoryCriterion(
                $scenario->repository,
                array_map( 'intval', explode('-', $dataMap['criterion_category']->toString()))
            );
        }
        if (isset($dataMap['criterion_area']) && $dataMap['criterion_area']->hasContent()) {
            $scenario->criteria[] = new Criteria\AreaCriterion(
                $scenario->repository,
                array_map( 'intval', explode('-', $dataMap['criterion_area']->toString()))
            );
        }
        if (isset($dataMap['criterion_reporter_group']) && $dataMap['criterion_reporter_group']->hasContent()) {
            $scenario->criteria[] = new Criteria\ReporterGroupCriterion(
                $scenario->repository,
                array_map( 'intval', explode('-', $dataMap['criterion_reporter_group']->toString()))
            );
        }

        if (isset($dataMap['random_owner'])) {
            $scenario->useRandomOwner = $dataMap['random_owner']->attribute('data_int') == 1;
        }
        if (isset($dataMap['reporter_as_approver'])) {
            $scenario->makeReporterAsApprover = $dataMap['reporter_as_approver']->attribute('data_int') == 1;
        }
        if (isset($dataMap['reporter_as_owner'])) {
            $scenario->makeReporterAsOwner = $dataMap['reporter_as_owner']->attribute('data_int') == 1;
        }
        if (isset($dataMap['reporter_as_observer'])) {
            $scenario->makeReporterAsObserver = $dataMap['reporter_as_observer']->attribute('data_int') == 1;
        }

        if (isset($dataMap['expiry']) && $dataMap['expiry']->hasContent()) {
            $scenario->expiry = (int)$dataMap['expiry']->toString();
        }

        return $scenario;
    }

    public static function fromArray(Repository $repository, $data)
    {
        $scenario = new SensorScenario($repository);
        $scenario->id = $data['id'];
        $scenario->triggers = $data['triggers'];
        if (is_array($data['assignments']['approver'])) {
            $scenario->approversIdList = array_column($data['assignments']['approver'], 'id');
        }
        if (is_array($data['assignments']['owner_group'])) {
            $scenario->ownerGroupsIdList = array_column($data['assignments']['owner_group'], 'id');
        }
        if (is_array($data['assignments']['owner'])) {
            $scenario->ownersIdList =  array_column($data['assignments']['owner'], 'id');
        }
        if (is_array($data['assignments']['observer'])) {
            $scenario->observersIdList =  array_column($data['assignments']['observer'], 'id');
        }
        if (is_array($data['assignments']['category'])) {
            $scenario->category = $data['assignments']['category']['id'];
        }
        $scenario->useRandomOwner = (bool)$data['assignments']['random_owner'];
        $scenario->makeReporterAsApprover = (bool)$data['assignments']['reporter_as_approver'];
        $scenario->makeReporterAsOwner = (bool)$data['assignments']['reporter_as_owner'];
        $scenario->makeReporterAsObserver = (bool)$data['assignments']['reporter_as_observer'];
        if (intval($data['expiry']) > 0) {
            $scenario->expiry = intval($data['expiry']);
        }
        foreach ($data['criteria'] as $type => $values){
            if ($type === 'type'){
                $scenario->criteria[] = new Criteria\TypeCriterion(
                    $scenario->repository,
                    $values
                );
            }
            if ($type === 'category'){
                $scenario->criteria[] = new Criteria\CategoryCriterion(
                    $scenario->repository,
                    $values
                );
            }
            if ($type === 'area'){
                $scenario->criteria[] = new Criteria\AreaCriterion(
                    $scenario->repository,
                    $values
                );
            }
            if ($type === 'reporter_group'){
                $scenario->criteria[] = new Criteria\ReporterGroupCriterion(
                    $scenario->repository,
                    $values
                );
            }
        }

        return $scenario;
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
            $group = $this->repository->getGroupService()->loadGroup($ownerGroupsId, []);
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
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next'], []);
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
        foreach ($data['assignments'] as $role => $assignments){
            if ($role === 'category'){
                if ($assignments){
                    try {
                        $data['assignments'][$role] = $this->repository->getCategoryService()->loadCategory($assignments);
                    }catch (NotFoundException $e){}
                }
            }elseif (in_array($role, ['approver', 'owner_group', 'owner', 'observer'])){
                $users = [];
                foreach ($assignments as $id) {
                    $user = $this->repository->getUserService()->loadUser($id);
                    if (!$user->email) {
                        $user = $this->repository->getGroupService()->loadGroup($id, []);
                    }
                    $users[] = $user;
                }
                $data['assignments'][$role] = $users;
            }
        }
        $data['assignments']['reporter_as_approver'] = $this->makeReporterAsApprover;
        $data['assignments']['reporter_as_owner'] = $this->makeReporterAsOwner;
        $data['assignments']['reporter_as_observer'] = $this->makeReporterAsObserver;
        $data['assignments']['random_owner'] = $this->useRandomOwner;

        return $data;
    }

    public static function getAvailableEvents($locale = null)
    {
        return [
            'on_create' => Translator::translate('Creating a issue', 'scenario', [], $locale),
            'on_set_type' => Translator::translate('Type assignment to issue', 'scenario', [], $locale),
            'on_add_category' => Translator::translate('Category assignment to issue', 'scenario', [], $locale),
            'on_add_area' => Translator::translate('Zone assignment to issue', 'scenario', [], $locale),
            'on_fix' => Translator::translate('Fixing the issue', 'scenario', [], $locale),
            'on_close' => Translator::translate('Closing the issue', 'scenario', [], $locale),
        ];
    }

    /**
     * @todo Al momento l'audit message viene scritto in italiano
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
        foreach ($assignments as $role => $data){
            $roleName = false;
            if ($role == 'approver'){
                $roleName = 'Riferimento';
            }elseif ($role == 'owner_group'){
                $roleName = 'Gruppo incaricato';
            }elseif ($role == 'owner'){
                $roleName = 'Incaricato';
            }elseif ($role == 'observer'){
                $roleName = 'Osservatore';
            }elseif ($role == 'category'){
                $roleName = 'Categoria';
            }
            if ($roleName){
                $nameList = [];
                if (is_array($data)) {
                    foreach ($data as $item) {
                        $nameList[] = $item->name;
                    }
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
                }elseif ($role == 'category' && $data instanceof Post\Field\Category){
                    $nameList[] = $data->name;
                }
                if (!empty($nameList)) {
                    $details[] = ' • ' . $roleName . ': ' . implode(', ', $nameList);
                }
            }
        }
        $availableEvents = self::getAvailableEvents('ita-IT');
        $eventMessage = strtolower($availableEvents[$trigger]);

        $criteriaMessages = [];
        foreach ($this->criteria as $criterion){
            $criteriaMessages[] = $criterion->getDescription();
        }
        $criteriaMessage = implode(' e ', $criteriaMessages);

        if (!empty($this->expiry)) {
            $details[] = ' • Imposta scadenza a ' . $this->expiry . ' giorni';
        }

        $this->currentPost = $currentPost;

        return "In seguito alla {$eventMessage} {$criteriaMessage}, viene eseguita l'assegnazione automatica #{$this->id}: " . implode(" ", $details);
    }
}
