<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;

class AvgTimes extends StatisticFactory
{
    use FiltersTrait;

    protected $repository;

    private $data;

    /**
     * StatusPercentage constructor.
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'timesAvg';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Media tempi di esecuzione');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Media dei tempi di esecuzione in giornate');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $search = $this->repository->getStatisticsService()->searchPosts("{$categoryFilter}{$areaFilter} workflow_status in [closed] and stats [field=>[sensor_fix_close_time_i,sensor_assign_fix_time_i,sensor_read_assign_time_i,sensor_open_read_time_i],facet=>{$byInterval}] limit 1");
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            foreach ($search->stats['stats_fields'] as $name => $values) {
                $item = [
                    'name' => $this->getTimeName($name),
                    'data' => []
                ];
                $item['data'][] = [
                    'interval' => 'all',
                    'count' => $values['count'],
                    'avg' => $values['count'] > 0 ? $this->secondsInDay($values['sum'] / $values['count']) : 0
                ];
                $this->data['intervals']['all'] = 'all';

                foreach ($values['facets'] as $facetValues) {
                    foreach ($facetValues as $interval => $facetValue) {
                        $intervalName = is_callable($intervalNameParser) ? $intervalNameParser($interval) : $interval;
                        $this->data['intervals'][$interval] = $intervalName;
                        $item['data'][] = [
                            'interval' => $intervalName,
                            'count' => $facetValue['count'],
                            'avg' => $facetValue['count'] > 0 ? $this->secondsInDay($facetValue['sum'] / $facetValue['count']) : 0
                        ];
                    }
                }
                usort($item['data'], function ($a, $b) {
                    return strcmp($a["interval"], $b["interval"]);
                });
                $this->data['series'][] = $item;
            }
            $this->data['intervals'] = array_values($this->data['intervals']);
            usort($this->data['intervals'], function ($a, $b) {
                return strcmp($a, $b);
            });
        }

        return $this->data;
    }

    private function getTimeName($name)
    {
        if ($name == 'sensor_open_read_time_i') $name = ezpI18n::tr('sensor/chart', 'Lettura');
        if ($name == 'sensor_read_assign_time_i') $name = ezpI18n::tr('sensor/chart', 'Assegnazione');
        if ($name == 'sensor_assign_fix_time_i') $name = ezpI18n::tr('sensor/chart', 'Lavorazione');
        if ($name == 'sensor_fix_close_time_i') $name = ezpI18n::tr('sensor/chart', 'Chiusura');

        return $name;
    }

    private function secondsInDay($seconds)
    {
        return round($seconds / 3600 / 24, 1);
    }
}