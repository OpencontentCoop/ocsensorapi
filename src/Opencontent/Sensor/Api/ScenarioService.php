<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Api\Values\Scenario\SearchScenarioParameters;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;

interface ScenarioService
{
    const INIT_POST = 'init_post';

    /**
     * @return Scenario[]
     */
    public function loadScenarios();

    /**
     * @param array $parameters
     * @return mixed
     */
    public function searchScenarios(array $parameters);

    /**
     * @param $struct
     * @return Scenario
     */
    public function createScenario($struct);

    /**
     * @param $id
     * @param $struct
     * @return bool
     */
    public function editScenario($id, $struct);

    /**
     * @param $trigger
     * @return Scenario[]
     */
    public function getScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null);

    /**
     * @param $trigger
     * @return Scenario
     */
    public function getFirstScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null);

    /**
     * @param Scenario $scenario
     * @param Post $post
     * @return integer number of matches (-1 means no match)
     */
    public function match(Scenario $scenario, Post $post);

    /**
     * @param Scenario $scenario
     * @param Post $post
     * @return void
     */
    public function applyScenario(Scenario $scenario, Post $post, $trigger);
}