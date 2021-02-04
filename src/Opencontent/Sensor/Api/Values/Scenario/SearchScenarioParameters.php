<?php

namespace Opencontent\Sensor\Api\Values\Scenario;

class SearchScenarioParameters
{
    public $withApprovers = false;

    public $withOwnerGroups = false;

    public $withOwners = false;

    public $withObservers = false;

    public function __construct($withApprovers = false, $withOwnerGroups = false, $withOwners = false, $withObservers = false)
    {
        $this->withApprovers = $withApprovers;
        $this->withOwnerGroups = $withOwnerGroups;
        $this->withOwners = $withOwners;
        $this->withObservers = $withObservers;
    }
}