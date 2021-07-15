<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils;

class StatusPercentage extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    protected $data;

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
        return 'status';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Stato');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Numero di segnalazioni per stato');
    }

    protected function getStatusHistory($query, $gap, $start = null, $end = null)
    {
        $result = [
            'intervals' => [],
            'series' => [],
        ];
        
        try {
            $dateBounds = \SensorOperator::getPostsDateBounds();
            $startRange = $dateBounds['first']->format('Y') . '-01-01';
            $endRange = $dateBounds['last']->format('Y') . '-12-31';
            $availableDates = [];

            $newSearch = $this->repository->getStatisticsService()->searchPosts(
                "{$query}
                    facets [raw[sensor_status_lk]|alpha] facet_range [field=>meta_published_dt,start=>{$startRange},end=>{$endRange},gap=>{$gap}] limit 1"
            );
            $newCounts = $newSearch->facet_range['meta_published_dt']['counts'];
            $availableDates = array_unique(array_merge($availableDates, array_keys($newCounts)));
            $global = [];
            if (isset($newSearch->facets[0])) {
                foreach ($newSearch->facets[0]['data'] as $status => $count) {
                    $global[$status] = $count;
                }
            }

            $readSearch = $this->repository->getStatisticsService()->searchPosts(
                "{$query}
                    (raw[sensor_is_read_i] range ['1','*'] or raw[sensor_is_assigned_i] range ['1','*']) and
                    facets [raw[sensor_status_lk]] facet_range [field=>sensor_read_dt,start=>{$startRange},end=>{$endRange},gap=>{$gap}] limit 1"
            );
            $readCounts = isset($readSearch->facet_range['sensor_read_dt']['counts']) ?
                $readSearch->facet_range['sensor_read_dt']['counts'] : [];
            $availableDates = array_unique(array_merge($availableDates, array_keys($readCounts)));

            $onlyAssignedSearch = $this->repository->getStatisticsService()->searchPosts(
                "{$query}
                    raw[sensor_is_read_i] = 0 and raw[sensor_is_assigned_i] range ['1','*'] and
                    facets [raw[sensor_status_lk]] facet_range [field=>sensor_assigned_dt,start=>{$startRange},end=>{$endRange},gap=>{$gap}] limit 1"
            );
            $onlyAssignedCounts = isset($onlyAssignedSearch->facet_range['sensor_assigned_dt']['counts']) ?
                $onlyAssignedSearch->facet_range['sensor_assigned_dt']['counts'] : [];
            $availableDates = array_unique(array_merge($availableDates, array_keys($onlyAssignedCounts)));

            $closeSearch = $this->repository->getStatisticsService()->searchPosts(
                "{$query}
                    raw[sensor_status_lk] = 'close' and raw[sensor_is_closed_i] range ['1','*'] and
                    facets [raw[sensor_status_lk]] facet_range [field=>sensor_close_dt,start=>{$startRange},end=>{$endRange},gap=>{$gap}] limit 1"
            );
            $closeCounts = isset($closeSearch->facet_range['sensor_close_dt']['counts']) ?
                $closeSearch->facet_range['sensor_close_dt']['counts'] : [];
            $availableDates = array_unique(array_merge($availableDates, array_keys($closeCounts)));
            sort($availableDates);

            if ($start && $start != '*') {
                $time = new \DateTime($start, new \DateTimeZone('UTC'));
                if (!$time instanceof \DateTime) {
                    throw new \Exception("Problem with date $start");
                }
                $start = $time->format('U');
            }

            if ($end && $end != '*') {
                $time = new \DateTime($end, new \DateTimeZone('UTC'));

                if (!$time instanceof \DateTime) {
                    throw new \Exception("Problem with date $end");
                }
                $end = $time->format('U');
            }
            $hasBounds = $start && $end;

            $data = [];
            $current = [
                'pending' => 0,
                'open' => 0,
                'close' => 0,
            ];
            $intervals = [];
            foreach ($availableDates as $date){
                $new = isset($newCounts[$date]) ? $newCounts[$date] : 0;
                $closed = isset($closeCounts[$date]) ? $closeCounts[$date] : 0;
                $read = isset($readCounts[$date]) ? $readCounts[$date] : 0;
                if (isset($onlyAssignedCounts[$date])){
                    $read += $onlyAssignedCounts[$date];
                }
                $pending = $new - $read;
                $open = $new - $closed - $pending;
                $item = [
                    'new' => $new,
                    'pending' => $current['pending'] + $pending,
                    'open' => $current['open'] + $open,
                    'close' => $current['close'] + $closed,
                ];
                $timestamp = (new \DateTime($date, Utils::getDateTimeZone()))->format('U');
                $data[$timestamp] = $item;
                $current = $item;
            }
            $series = [
                [
                    'name' => $this->repository->getSensorPostStates('sensor')['sensor.close']->attribute('current_translation')->attribute('name'),
                    'color' => $this->getColor('close'),
                    'data' => []
                ],
                [
                    'name' => $this->repository->getSensorPostStates('sensor')['sensor.open']->attribute('current_translation')->attribute('name'),
                    'color' => $this->getColor('open'),
                    'data' => []
                ],
                [
                    'name' => $this->repository->getSensorPostStates('sensor')['sensor.pending']->attribute('current_translation')->attribute('name'),
                    'color' => $this->getColor('pending'),
                    'data' => []
                ],
            ];
            $intervals = [];
            foreach ($data as $interval => $datum){
                if ($hasBounds && ($interval < $start || $interval > $end)){
                    continue;
                }
                $intervals[] = $interval;
                $series[2]['data'][] = [
                    'interval' => $interval,
                    'count' => $datum['pending']
                ];
                $series[1]['data'][] = [
                    'interval' => $interval,
                    'count' => $datum['open']
                ];
                $series[0]['data'][] = [
                    'interval' => $interval,
                    'count' => $datum['close']
                ];
            }
            $intervals[] = 'all';
            $result['intervals'] = $intervals;

            $series[2]['data'][] = [
                'interval' => 'all',
                'count' => isset($global['pending']) ? $global['pending'] : 0
            ];
            $series[1]['data'][] = [
                'interval' => 'all',
                'count' => isset($global['open']) ? $global['open'] : 0
            ];
            $series[0]['data'][] = [
                'interval' => 'all',
                'count' => isset($global['close']) ? $global['close'] : 0
            ];

            $result['series'] = $series;
        }catch (\Exception $e){
            $this->repository->getLogger()->error($e->getMessage());
        }

        return $result;
    }

    public function getData()
    {
        if ($this->data === null) {
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();
            $this->data = $this->getStatusHistory(
                "{$categoryFilter}{$areaFilter}{$groupFilter}{$typeFilter}",
                $this->getGapFilter(),
                $this->getParameter('start'),
                $this->getParameter('end')
            );

        }

        return $this->data;
    }

}