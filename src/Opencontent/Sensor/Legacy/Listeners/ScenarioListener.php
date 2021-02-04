<?php

namespace Opencontent\Sensor\Legacy\Listeners;

use League\Event\AbstractListener;
use League\Event\EventInterface;
use Opencontent\Sensor\Api\Values\Event as SensorEvent;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Scenarios\SensorScenario;

class ScenarioListener extends AbstractListener
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function handle(EventInterface $event, $param = null)
    {
        if ($param instanceof SensorEvent) {
            if (!in_array($param->identifier, array_keys(SensorScenario::getAvailableEvents()))){
                return;
            }
            if ($param->post instanceof Post) {
                $scenarios = $this->repository->getScenarioService()->getScenariosByTrigger($param->post, $param->identifier);
                $matchesScenario = [];
                foreach ($scenarios as $scenario){
                    $matches = $this->repository->getScenarioService()->match($scenario, $param->post);
                    if ($matches > 0){
                        $matchesScenario[$matches][$scenario->id] = $scenario;
                    }
                }
                krsort($matchesScenario);
                if (count($matchesScenario)) {
                    $scenarios = array_shift($matchesScenario);
                    ksort($scenarios);
                    foreach ($scenarios as $scenario) {
                        $this->repository->getScenarioService()->applyScenario($scenario, $param->post, $param->identifier);
                        break;
                    }
                }
            }
        }
    }
}