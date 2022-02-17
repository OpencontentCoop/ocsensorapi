<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\InvalidArgumentException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\SearchScenarioParameters;
use Opencontent\Sensor\Core\ScenarioService as BaseScenarioService;
use Opencontent\Sensor\Legacy\Scenarios\FallbackScenario;
use Opencontent\Sensor\Legacy\Scenarios\FirstAreaApproverScenario;
use Opencontent\Sensor\Legacy\Scenarios\NullScenario;
use Opencontent\Sensor\Legacy\Scenarios\SensorScenario;
use Opencontent\Sensor\Legacy\Utils\TreeNode;

class ScenarioService extends BaseScenarioService
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    private $scenarioSearchLimit = 300;

    private $scenariosByTrigger;

    public function getClassIdentifierAsString()
    {
        return 'sensor_scenario';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getScenariosRootNode()->attribute('node_id');
    }

    public function getFirstScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null)
    {
        $scenarios = $this->getScenariosByTrigger($post, $trigger, $parameters);
        if (empty($scenarios)) {
            return new NullScenario();
        }

        return array_shift($scenarios);
    }

    public function getScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null)
    {
        $scenarios = [];
        if ($trigger == self::INIT_POST) {
            $loadedScenarios = $this->loadInitScenarios();
        } else {
            if ($this->repository->getSensorSettings()->get('ScenarioCache')) {
                $loadedScenarios = $this->getScenariosByTriggerFromCache($trigger);
            }else{
                $loadedScenarios = $this->searchScenarios(['trigger' => $trigger]);
            }
        }
        foreach ($loadedScenarios as $scenario) {
            $scenario->setCurrentPost($post);
            $isValid = false;
            if (in_array($trigger, $scenario->triggers)) {
                $isValid = true;
                if ($parameters instanceof SearchScenarioParameters) {
                    if ($isValid && $parameters->withApprovers) {
                        $isValid = $scenario->hasApprovers();
                    }
                    if ($isValid && $parameters->withOwnerGroups) {
                        $isValid = $scenario->hasOwnerGroups();
                    }
                    if ($isValid && $parameters->withOwners) {
                        $isValid = $scenario->hasOwners();
                    }
                    if ($isValid && $parameters->withObservers) {
                        $isValid = $scenario->hasObservers();
                    }
                }
            }
            if ($isValid) {
                $scenarios[] = $scenario;
            }
        }

        return $scenarios;
    }

    private function getScenariosByTriggerFromCache($trigger)
    {
        if ($this->scenariosByTrigger === null) {
            $this->scenariosByTrigger = [];
            $scenarioRoot = $this->repository->getScenariosRootNode();
            if ($scenarioRoot instanceof \eZContentObjectTreeNode) {
                $rootId = $scenarioRoot->attribute('node_id');
                $modified = $scenarioRoot->attribute('modified_subnode');
                $data = self::getCacheManager()->processCache(
                    function ($file, $mtime, $extraData) {
                        if ($mtime >= $extraData[0]) {
                            $content = include($file);
                            return $content;
                        } else {
                            return new \eZClusterFileFailure(1, "Modified timestamp greater then file mtime");
                        }
                    },
                    function ($file, $args) {
                        $data = [];
                        /** @var \eZContentObjectTreeNode[] $nodes */
                        $nodes = \eZContentObjectTreeNode::subTreeByNodeID([
                            'ClassFilterType' => 'include',
                            'ClassFilterArray' => ['sensor_scenario'],
                            'Limitation' => []
                        ], $args[1]);
                        foreach ($nodes as $node) {
                            $data[] = SensorScenario::fromContentObject($this->repository, $node->object());
                        }
                        return [
                            'content' => json_encode($data),
                            'scope' => 'sensor-scenarios',
                            'datatype' => 'php',
                            'store' => true
                        ];
                    },
                    null, null, [$modified, $rootId]
                );

                $scenariosList = json_decode($data, 1);
                foreach ($scenariosList as $scenario) {
                    foreach ($scenario['triggers'] as $trigger) {
                        $this->scenariosByTrigger[$trigger][$scenario['id']] = SensorScenario::fromArray($this->repository, $scenario);
                    }
                }
            }
        }

        if (isset($this->scenariosByTrigger[$trigger])){
            ksort($this->scenariosByTrigger[$trigger]);
            return array_values($this->scenariosByTrigger[$trigger]);
        }

        return [];
    }

    private static function getCacheManager()
    {
        $cacheFile = 'scenarios.cache';
        $cacheFilePath = \eZDir::path(array(\eZSys::cacheDirectory(), 'ocopendata', 'sensor', 'scenarios', $cacheFile));
        return \eZClusterFileHandler::instance($cacheFilePath);
    }

    public function loadInitScenarios()
    {
        if ($this->initScenarios === null) {
            $this->initScenarios = [
                new FirstAreaApproverScenario($this->repository),
                new FallbackScenario(),
            ];
        }

        return $this->initScenarios;
    }

    public function searchScenarios(array $parameters)
    {
        $query = '';
        if (isset($parameters['trigger'])) {
            $query .= "triggers = '" . addcslashes($parameters['trigger'], "')([]") . "' and ";
        }
        if (isset($parameters['type'])) {
            $query .= "criterion_type = '" . addcslashes($parameters['type'], "')([]") . "' and ";
        }
        if (isset($parameters['category'])) {
            $query .= "criterion_category.id = '" . addcslashes($parameters['category'], "')([]") . "' and ";
        }
        if (isset($parameters['area'])) {
            $query .= "criterion_area.id = '" . addcslashes($parameters['area'], "')([]") . "' and ";
        }
        if (isset($parameters['reporter_group'])) {
            $query .= "criterion_reporter_group.id = '" . addcslashes($parameters['reporter_group'], "')([]") . "' and ";
        }
        $query .= "sort [id=>asc] limit {$this->scenarioSearchLimit}";

        $scenarios = [];

        $this->setEnvironmentSettings(new \FullEnvironmentSettings(['maxSearchLimit' => $this->scenarioSearchLimit]));
        $result = $this->search($query, []);
        foreach ($result->searchHits as $item) {
            $scenarios[] = SensorScenario::fromContentObject($this->repository, \eZContentObject::fetch((int)$item['metadata']['id']));
        }
        while ($result->nextPageQuery){
            $result = $this->search($result->nextPageQuery, []);
            foreach ($result->searchHits as $item) {
                $scenarios[] = SensorScenario::fromContentObject($this->repository, \eZContentObject::fetch((int)$item['metadata']['id']));
            }
        };

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

        if ($object instanceof \eZContentObject) {
            TreeNode::clearCache($this->repository->getCategoriesRootNode()->attribute('node_id'));
        }

        return SensorScenario::fromContentObject($this->repository, $object);
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
            'expiry' => isset($struct['expiry']) && intval($struct['expiry']) > 0 ? intval($struct['expiry']) : ''
        ];
    }

    public function editScenario($id, $struct)
    {
        if (\eZUser::currentUser()->hasAccessTo('sensor', 'config')['accessWord'] == 'no') { //@todo
            throw new ForbiddenException('edit', 'scenario');
        }

        $scenario = \eZContentObject::fetch((int)$id);
        if (!$scenario instanceof \eZContentObject) {
            throw new NotFoundException("Scenario $id");
        }
        $attributes = $this->loadStruct($struct);
        $remoteId = SensorScenario::generateRemoteId($attributes);
        $fixRemoteId = false;
        if ($scenario->attribute('remote_id') != $remoteId) {
            $exists = \eZContentObject::fetchByRemoteID($remoteId);
            if ($exists instanceof \eZContentObject) {
                throw new InvalidArgumentException("Scenario already exists");
            }
            $fixRemoteId = true;
        }
        $params = [
            'attributes' => $attributes
        ];
        if ($fixRemoteId) {
            $params['remote_id'] = $remoteId;
        }

        $update = \eZContentFunctions::updateAndPublishObject($scenario, $params);
        if ($update) {
            TreeNode::clearCache($this->repository->getCategoriesRootNode()->attribute('node_id'));
        }

        return $update;
    }
}
