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

    /**
     * @param StatisticFactory[] $factories
     * @return void
     */
    public function setStatisticFactories($factories);

    /**
     * @param mixed $query
     * @param array $parameters
     * @return mixed
     */
    public function searchPosts($query, $parameters = []);
}
