<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\StatisticFactory;

class StatisticsService extends \Opencontent\Sensor\Core\StatisticsService
{
    /**
     * @var StatisticFactory[]
     */
    private $factories = [];

    private $collectQueries = false;

    private $queries = [];

    public function getStatisticFactories($ignorePolicies = false)
    {
        if ($ignorePolicies){
            return $this->factories;
        }

        $user = \eZUser::currentUser();
        $module = 'sensor';
        $function = 'stat';
        $accessArray = $user->accessArray();

        if (isset($accessArray['*']['*']) || isset($accessArray[$module]['*'])) {

            return $this->factories;

        } elseif (isset($accessArray[$module][$function])) {
            $accessibleChartIdentifiers = [];
            $policies = $accessArray[$module][$function];
            foreach ($policies as $policyIdentifier => $limitationArray) {
                foreach ((array)$limitationArray as $limitationIdentifier => $limitationValues) {
                    switch ($limitationIdentifier) {
                        case '*':
                            if ($limitationValues == '*') {
                                return $this->factories;
                            }
                            break;

                        case 'ChartList':
                            foreach ($limitationValues as $identifier) {

                                if ($identifier == '*') {
                                    return $this->factories;
                                    break;
                                }
                                try {
                                    $accessibleChartIdentifiers[] = $identifier;
                                }catch (\Exception $e){
                                    $this->repository->getLogger()->error($e->getMessage(), [__METHOD__]);
                                }
                            }
                            break;
                    }
                }
            }

            $accessibleCharts = [];
            foreach ($this->factories as $factory){
                if (in_array($factory->getIdentifier(), $accessibleChartIdentifiers)){
                    $accessibleCharts[] = $factory;
                }
            }

            return $accessibleCharts;
        }

        return [];
    }

    public function setStatisticFactories($factories)
    {
        $this->factories = $factories;
    }

    public function getStatisticFactoryByIdentifier($identifier)
    {
        foreach ($this->getStatisticFactories() as $factory){
            if ($factory->getIdentifier() == $identifier){

                return $factory->init();
            }
        }

        throw new NotFoundException("Stat $identifier not found");
    }

    public function searchPosts($query, $parameters = array())
    {
        if ($this->collectQueries) {
            $cleanQuery = preg_replace("/\r|\n|\s+/", " ", $query);
            $this->queries[] = ['query' => trim($cleanQuery), 'parameters' => $parameters];
        }
        return $this->repository->getSearchService()->searchPosts($query, $parameters, []);
    }

    public function startCollectQueries()
    {
        $this->queries = [];
        $this->collectQueries = true;
    }

    public function stopCollectQueries()
    {
        $this->collectQueries = false;
    }

    public function getCollectedQueries()
    {
        return $this->queries;
    }
}
