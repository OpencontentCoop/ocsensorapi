<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Core\ScenarioService as BaseScenarioService;
use Opencontent\Sensor\Legacy\Scenarios\FallbackScenario;
use Opencontent\Sensor\Legacy\Scenarios\FirstAreaApproverScenario;
use Opencontent\Sensor\Legacy\Scenarios\NullScenario;
use Opencontent\Sensor\Api\Values\Scenario\SearchScenarioParameters;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;
use Opencontent\Sensor\Legacy\Scenarios\SensorScenario;

class ScenarioService extends BaseScenarioService
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    private $scenarios;

    public function loadScenarios()
    {
        if ($this->scenarios === null) {
            $scenarios = [
                new FirstAreaApproverScenario($this->repository),
                new FallbackScenario(),
            ];

            $this->setEnvironmentSettings(new \FullEnvironmentSettings());
            $result = $this->search("sort [id=>asc] limit 300", []);
            $items = [];
            foreach ($result->searchHits as $item) {
                $scenarios[] = new SensorScenario($this->repository, (new Content($item))->getContentObject($this->repository->getCurrentLanguage()));
            }
//            foreach ($this->repository->getScenariosRootNode()->subTree([
//                'ClassFilterType' => 'include',
//                'ClassFilterArray' => ['sensor_scenario'],
//                'Limitation' => []
//            ]) as $item) {
//                $scenarios[] = new SensorScenario($this->repository, $item);
//            }

            $this->scenarios = $scenarios;
        }

        return $this->scenarios;
    }

    public function getClassIdentifierAsString()
    {
        return 'sensor_scenario';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getScenariosRootNode()->attribute('node_id');
    }

    public function getScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null)
    {
        $scenarios = [];
        foreach ($this->loadScenarios() as $scenario){
            $scenario->setCurrentPost($post);
            $isValid = false;
            if (in_array($trigger, $scenario->triggers)){
                $isValid = true;
                if ($parameters instanceof SearchScenarioParameters){
                    if ($isValid && $parameters->withApprovers){
                        $isValid = $scenario->hasApprovers();
                    }
                    if ($isValid && $parameters->withOwnerGroups){
                        $isValid = $scenario->hasOwnerGroups();
                    }
                    if ($isValid && $parameters->withOwners){
                        $isValid = $scenario->hasOwners();
                    }
                    if ($isValid && $parameters->withObservers){
                        $isValid = $scenario->hasObservers();
                    }
                }
            }
            if ($isValid){
                $scenarios[] = $scenario;
            }
        }

        return $scenarios;
    }

    public function getFirstScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null)
    {
        $scenarios = $this->getScenariosByTrigger($post, $trigger, $parameters);
        if (empty($scenarios)){
            return new NullScenario();
        }

        return array_shift($scenarios);
    }

    public function searchScenarios(array $parameters)
    {
        $query = '';
        if (isset($parameters['trigger'])){
            $query .= "triggers = '" . addcslashes($parameters['trigger'], "')([]") . "' and ";
        }
            if (isset($parameters['type'])){
            $query .= "criterion_type = '" . addcslashes($parameters['type'], "')([]") . "' and ";
        }
        if (isset($parameters['category'])){
            $query .= "criterion_category.id = '" . addcslashes($parameters['category'], "')([]") . "' and ";
        }
        if (isset($parameters['area'])){
            $query .= "criterion_area.id = '" . addcslashes($parameters['area'], "')([]") . "' and ";
        }
        if (isset($parameters['reporter_group'])){
            $query .= "criterion_reporter_group.id = '" . addcslashes($parameters['reporter_group'], "')([]") . "' and ";
        }
        $query .= "sort [id=>asc] limit 300";

        $this->setEnvironmentSettings(new \FullEnvironmentSettings());
        $result = $this->search($query, []);
        $scenarios = [];
        foreach ($result->searchHits as $item) {
            $scenarios[] = new SensorScenario($this->repository, (new Content($item))->getContentObject($this->repository->getCurrentLanguage()));
        }

        return $scenarios;
    }

    public function createScenario($struct)
    {
        $attributes = $this->loadStruct($struct);
        $remoteId = SensorScenario::generateRemoteId($attributes);
        $exists = \eZContentObject::fetchByRemoteID($remoteId);
        if ($exists instanceof \eZContentObject) {
            throw new InvalidArgumentException("Scenario already exists");
        }
        $object = \eZContentFunctions::createAndPublishObject([
            'parent_node_id' => $this->repository->getScenariosRootNode()->attribute('node_id'),
            'class_identifier' => 'sensor_scenario',
            'remote_id' => $remoteId,
            'attributes' => $attributes
        ]);

        return new SensorScenario($this->repository, $object);
    }

    public function editScenario($id, $struct)
    {
        $scenario = \eZContentObject::fetch((int) $id);
        if (!$scenario instanceof \eZContentObject){
            throw new NotFoundException("Scenario $id");
        }
        $attributes = $this->loadStruct($struct);
        $remoteId = SensorScenario::generateRemoteId($attributes);
        $fixRemoteId = false;
        if ($scenario->attribute('remote_id') != $remoteId){
            $exists = \eZContentObject::fetchByRemoteID($remoteId);
            if ($exists instanceof \eZContentObject) {
                throw new InvalidArgumentException("Scenario already exists");
            }
            $fixRemoteId = true;
        }
        $params = [
            'attributes' => $attributes
        ];
        if ($fixRemoteId){
            $params['remote_id'] = $remoteId;
        }

        return \eZContentFunctions::updateAndPublishObject($scenario, $params);
    }

    private function loadStruct($struct)
    {
        return [
            'approver' => isset($struct['assignments']['approver']) ? implode('-', $struct['assignments']['approver']) : null,
            'owner_group' => isset($struct['assignments']['owner_group']) ? implode('-', $struct['assignments']['owner_group']) : null,
            'owner' => isset($struct['assignments']['owner']) ? implode('-', $struct['assignments']['owner']) : null,
            'observer' => isset($struct['assignments']['observer']) ? implode('-', $struct['assignments']['observer']) : null,
            'triggers' => isset($struct['triggers']) ? implode('|', $struct['triggers']) : null,
            'criterion_type' => isset($struct['criteria']['type']) ? implode('|', $struct['criteria']['type']) : null,
            'criterion_category' => isset($struct['criteria']['category']) ? implode('-', $struct['criteria']['category']) : null,
            'criterion_area' => isset($struct['criteria']['area']) ? implode('-', $struct['criteria']['area']) : null,
            'criterion_reporter_group' => isset($struct['criteria']['reporter_group']) ? implode('-', $struct['criteria']['reporter_group']) : null,
            'random_owner' => isset($struct['assignments']['random_owner']) ? $struct['assignments']['random_owner'] : 0,
            'reporter_as_approver' => isset($struct['assignments']['reporter_as_approver']) ? $struct['assignments']['reporter_as_approver'] : 0,
            'reporter_as_owner' => isset($struct['assignments']['reporter_as_owner']) ? $struct['assignments']['reporter_as_owner'] : 0,
            'reporter_as_observer' => isset($struct['assignments']['reporter_as_observer']) ? $struct['assignments']['reporter_as_observer'] : 0,
        ];
    }
}