<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\ScenarioService as CoreScenarioService;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Scenario\SearchScenarioParameters;

class ScenarioService extends CoreScenarioService
{

    /**
     * @inheritDoc
     */
    public function loadInitScenarios()
    {
        // TODO: Implement loadInitScenarios() method.
    }

    /**
     * @inheritDoc
     */
    public function searchScenarios(array $parameters)
    {
        // TODO: Implement searchScenarios() method.
    }

    /**
     * @inheritDoc
     */
    public function createScenario($struct)
    {
        // TODO: Implement createScenario() method.
    }

    /**
     * @inheritDoc
     */
    public function editScenario($id, $struct)
    {
        // TODO: Implement editScenario() method.
    }

    /**
     * @inheritDoc
     */
    public function getScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null)
    {
        // TODO: Implement getScenariosByTrigger() method.
    }

    /**
     * @inheritDoc
     */
    public function getFirstScenariosByTrigger(Post $post, $trigger, SearchScenarioParameters $parameters = null)
    {
        // TODO: Implement getFirstScenariosByTrigger() method.
    }
}