<?php

namespace Opencontent\Sensor\Legacy\Statistics;

interface SinglePointQueryCapableInterface
{
    public function getSinglePointQuery($category, $serie);
}
