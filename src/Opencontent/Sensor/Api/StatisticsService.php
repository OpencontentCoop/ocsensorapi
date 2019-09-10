<?php

namespace Opencontent\Sensor\Api;


interface StatisticsService
{
    /**
     * @param $ignorePolicies
     * @return StatisticFactory[]
     */
    public function getStatisticFactories($ignorePolicies = false);

    /**
     * @param $identifier
     * @return StatisticFactory
     */
    public function getStatisticFactoryByIdentifier($identifier);

}