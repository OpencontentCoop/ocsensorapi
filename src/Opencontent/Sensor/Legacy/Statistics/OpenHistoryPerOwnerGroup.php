<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils;

class OpenHistoryPerOwnerGroup extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $description;

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
        return Translator::translate('Open over time', 'chart');
    }

    public function getDescription()
    {
        return ($this->description === null) ?
            Translator::translate('Open issues by entering date', 'chart') : $this->description;
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
            $ownerGroupFilter = '';//$this->getOwnerGroupFilter();
            $userGroupFilter = $this->getUserGroupFilter();
            $rangeFilter = $this->getRangeFilter();
            $hasGroupingFlag = $this->hasParameter('taggroup');
            $statusFilter = " raw[sensor_status_lk] = 'open' and ";
            $groupIdList = (array)$this->getParameter('group');

            $groupedGroups = $this->getGroupTree($hasGroupingFlag, $groupIdList);

            if ($this->hasParameter('group') && !$hasGroupingFlag) {
                $groupedGroups = $this->getGroupTree(true, $groupIdList);
                $idList = [];
                foreach ($groupedGroups as $groupedGroupId => $groupedGroup) {
                    if (count($groupedGroup['children'])) {
                        $idList = array_merge($idList, $groupedGroup['children']);
                    } else {
                        $idList[] = $groupedGroupId;
                    }
                }
                $idList = array_unique($idList);
                $pivotField = 'sensor_last_owner_user_id_i';
                $aggregations = $this->getOperatorsTree($idList);
                $queryPart = ' and raw[sensor_last_owner_group_id_i] in [' . implode(',', $idList) . ']';

            } else {
                $pivotField = 'sensor_last_owner_group_id_i';
                $aggregations = $groupedGroups;
                $idList = [];
                foreach ($groupedGroups as $groupedGroupId => $groupedGroup) {
                    if (count($groupedGroup['children'])) {
                        $idList = array_merge($idList, $groupedGroup['children']);
                    } else {
                        $idList[] = $groupedGroupId;
                    }
                }
                $idList = array_unique($idList);
                $queryPart = ' and raw[sensor_last_owner_group_id_i] in [' . implode(',', $idList) . ']';
            }

            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $query = "{$statusFilter}{$rangeFilter}{$categoryFilter}{$areaFilter}{$typeFilter}{$ownerGroupFilter}{$userGroupFilter}{$queryPart} facets [raw[{$pivotField}]|count|300] pivot [facet=>[{$byInterval},{$pivotField}],mincount=>1] limit 1";

            $search = $this->repository->getStatisticsService()->searchPosts($query);

            $intervals = [];
            $pivotData = [];
            foreach ($search->pivot["{$byInterval},{$pivotField}"] as $intervalPivot){
                $pivotItem = [];
                if (isset($intervalPivot['pivot'])){
                    foreach ($intervalPivot['pivot'] as $fieldPivot){
                        $pivotItem[$fieldPivot['value']] = $fieldPivot['count'];
                    }
                }
                $sum = array_sum($pivotItem);
                if ($sum > 0) {
                    $intervals[$intervalPivot['value']] = $intervalNameParser($intervalPivot['value']);
                    $pivotData[$intervalPivot['value']] = $pivotItem;
                }
            }

            ksort($intervals);
            ksort($pivotData);

            $data = [];
            foreach ($aggregations as $aggregationId => $aggregation) {
                if (count($aggregation['children'])) {
                    $idList = $aggregation['children'];
                } else {
                    $idList = [$aggregationId];
                }
                $series = [];
                foreach ($pivotData as $intervalId => $pivotDatum){
                    $keys = array_flip($idList);
                    $filteredData = array_intersect_key($pivotDatum, $keys);
                    $serie = [
                        'interval' => $intervals[$intervalId],
                        'count' => array_sum($filteredData),
                    ];
                    $series[] = $serie;
                }
                $data[$aggregation['name']] = [
                    'intervals' => array_values($intervals),
                    'serie' => $series,
                ];
            }

            if (isset($_GET['debug'])) {
                echo '<pre>';
                print_r([
                    $query,
                    $search->facets,
                    $search->pivot,
                    $aggregations,
                    $data,
                ]);
                die();
            }

            $this->data['intervals'] = $intervals;
            foreach ($data as $name => $datum) {
                $serie = $this->formatHistory($datum['serie'], $name, $this->getColor($name), $intervals);
                $this->data['series'][] = $serie;
            }
        }

        return $this->data;
    }

    private function formatHistory($data, $name, $color, $intervals = null)
    {
        if (is_array($intervals)) {
            $data = array_combine(array_column($data, 'interval'), array_column($data, 'count'));
            $fullfilledData = [];
            foreach ($intervals as $interval) {
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

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [];
        foreach ($data['series'] as $serie) {
            $item = [
                'name' => $serie['name'],
                'color' => isset($serie['color']) ? $serie['color'] : null,
                'data' => []
            ];
            foreach ($serie['data'] as $datum) {
                if ($datum['interval'] !== 'all') {
                    $item['data'][] = [
                        $datum['interval'] * 1000,
                        $datum['count']
                    ];
                }
            }
            $series[] = $item;
        }

        return [
            [
                'type' => 'highcharts',
                'config' => [
                    'chart' => [
                        'type' => 'column'
                    ],
                    'xAxis' => [
                        'type' => 'datetime',
                        'ordinal' => false,
                        'tickmarkPlacement' => 'on'
                    ],
                    'yAxis' => [
                        'min' => 0,
                        'title' => [
                            'text' => 'Numero'
                        ],
                        'allowDecimals' => false,
                    ],
                    'legend' => [
                        'enabled' => true,
                        'alignColumns' => false
                    ],
                    'tooltip' => [
                        'shared' => true,
                        'dateTimeLabelFormats' => [
                            'day' => '%b %e, %Y',
                            'hour' => '%b %e %Y',
                            'millisecond' => '%b %e %Y',
                            'minute' => '%b %e %Y',
                            'month' => '%B %Y',
                            'second' => '%b %e %Y',
                            'week' => '%b %e, %Y',
                            'year' => '%Y'
                        ],
                        'pointFormat' => '<span style="color:{point.color}">{series.name}</span>: <b>{point.y}</b><br/>'
                    ],
                    'plotOptions' => [
                        'column' => [
                            'dataLabels' => [
                                'enabled' => true,
                                'color' => 'white',
                                'style' => [
                                    'textShadow' => '0 0 3px black'
                                ]
                            ]
                        ],
                    ],
                    'title' => [
                        'text' => $this->getDescription()
                    ],
                    'series' => $series
                ]
            ]
        ];
    }
}
