<?php

namespace Opencontent\Sensor\Legacy\Scenarios;

use Opencontent\Sensor\Api\ScenarioService;
use Opencontent\Sensor\Api\Values\Scenario;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class FallbackScenario extends Scenario
{
    public function __construct()
    {
        $this->id = 0;
        $this->triggers = [ScenarioService::INIT_POST];
        $admin = \eZUser::fetchByName( 'admin' );
        if ($admin instanceof \eZUser){
            $this->approversIdList = [$admin->id()];
        }
    }
}