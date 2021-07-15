<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils;

class OpenHistoryPerOwnerGroup extends StatisticFactory
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
        return 'open_history';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Storico aperte per gruppo');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Storico delle segnalazioni aperte per gruppi di incaricati coinvolti');
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
            $typeFilter = $this->getTypeFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $rangeFilter = $this->getRangeFilter();
            $hasGroupingFlag = $this->hasParameter('taggroup');

            $nameAndQueryList = [];
            if ($this->hasParameter('group') && !$hasGroupingFlag) {
                $operators = $this->getOperatorsTree($this->getParameter('group'));
                foreach ($operators as $id => $operator) {
                    $nameAndQueryList[$operator['name']] =
                        "{$rangeFilter}{$categoryFilter}{$areaFilter}{$typeFilter}{$groupFilter}raw[sensor_last_owner_user_id_i] = '$id' and";
                }
            }elseif ($this->hasParameter('group') && $hasGroupingFlag) {
                $groupIdList = $this->getParameter('group');
                foreach ($groupIdList as $groupId) {
                    $group = $this->repository->getGroupService()->loadGroup($groupId);
                    if ($group instanceof Group) {
                        $groupFilter = "raw[sensor_last_owner_group_id_i] in ['{$group->id}'] and ";
                        $nameAndQueryList[$group->name] =
                            "{$rangeFilter}{$categoryFilter}{$areaFilter}{$typeFilter}{$groupFilter}";
                    }
                }
            } else {
                $groups = $this->getGroupTree($hasGroupingFlag);
                foreach ($groups as $id => $group){
                    $idList = array_merge(["'$id'"], $group['children']);
                    $groupFilter = 'raw[sensor_last_owner_group_id_i] in [' . implode(',', $idList) . '] and ';
                    $nameAndQueryList[$group['name']] =
                        "{$rangeFilter}{$categoryFilter}{$areaFilter}{$typeFilter}{$groupFilter}";
                }
                //$nameAndQueryList['Tutti i gruppi'] = "{$rangeFilter}{$categoryFilter}{$areaFilter}{$typeFilter}{$groupFilter}";
            }

            $intervals = [];
            $data = [];
            foreach ($nameAndQueryList as $name => $query){
                $datum = $this->getOpenHistory(
                    $query,
                    $this->getGapFilter(),
                    $this->getParameter('start'),
                    $this->getParameter('end')
                );
                if ($datum) {
                    $intervals = array_merge($intervals, $datum['intervals']);
                    $data[$name] = $datum;
                }
            }
            $intervals = array_unique($intervals);
            sort($intervals);
            $this->data['intervals'] = $intervals;
            foreach ($data as $name => $datum){
                $serie = $this->formatHistory($datum['serie'],$name, $this->getColor($name), $intervals);
                $this->data['series'][] = $serie;
            }
        }

        return $this->data;
    }

    protected function getOpenHistory($query, $gap, $start = null, $end = null)
    {
        $result = [
            'intervals' => [],
            'serie' => [],
        ];

        try {
            $dateBounds = \SensorOperator::getPostsDateBounds();
            $startRange = $dateBounds['first']->format('Y') . '-01-01';
            $endRange = $dateBounds['last']->format('Y') . '-12-31';

            if ($start && $start != '*') {
                $time = new \DateTime($start, new \DateTimeZone('UTC'));
                if (!$time instanceof \DateTime) {
                    throw new \Exception("Problem with date $start");
                }
                $startRange = $time->format('Y-m-d');
            }
            if ($end && $end != '*') {
                $time = new \DateTime($end, new \DateTimeZone('UTC'));

                if (!$time instanceof \DateTime) {
                    throw new \Exception("Problem with date $end");
                }
                $endRange = $time->format('Y-m-d');
            }

            $availableDates = [];

            $newSearch = $this->repository->getStatisticsService()->searchPosts(
                "{$query}
                    facets [raw[sensor_status_lk]|alpha] facet_range [field=>meta_published_dt,start=>{$startRange},end=>{$endRange},gap=>{$gap}] limit 1"
            );
            if (array_sum($newSearch->facets[0]['data']) === 0){
                return false;
            }
            $newCounts = $newSearch->facet_range['meta_published_dt']['counts'];
            $availableDates = array_unique(array_merge($availableDates, array_keys($newCounts)));
            sort($availableDates);

            $serie = [];
            $intervals = [];
            foreach ($availableDates as $date){
                $count = isset($newCounts[$date]) ? $newCounts[$date] : 0;
                $timestamp = (new \DateTime($date, Utils::getDateTimeZone()))->format('U');
                $serie[] = [
                    'interval' => $timestamp,
                    'count' => $count
                ];
                $intervals[] = $timestamp;
            }
            $result = [
                'intervals' => $intervals,
                'serie' => $serie,
            ];
        }catch (\Exception $e){
            $this->repository->getLogger()->error($e->getMessage());
        }

        return $result;

    }

    private function formatHistory($data, $name, $color, $intervals = null)
    {
        if (is_array($intervals)){
            $data = array_combine(array_column($data, 'interval'), array_column($data, 'count'));
            $fullfilledData = [];
            foreach ($intervals as $interval){
                $fullfilledData[] = [
                    'interval' => $interval,
                    'count' => isset($data[$interval]) ? $data[$interval] : 0
                ];
            }
            $data = $fullfilledData;
        }
        return [
            'name' => $name,
            'color' => $color,
            'data' => $data,
        ];
    }

}