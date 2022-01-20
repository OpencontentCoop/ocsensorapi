<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\SearchService;

class StatusPerOwnerGroup extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $minCount = 1;
    
    protected $addTotals = true;

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
        return 'status_owner_groups';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Stato per gruppo di incaricati');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Stato delle segnalazioni per gruppi di incaricati coinvolti');
    }

    public function getData()
    {
        if ($this->data === null) {
            $categoryFilter = $this->getCategoryFilter();
            $rangeFilter = $this->getRangeFilter();
            $areaFilter = $this->getAreaFilter();
            $ownerGroupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();
            $hasGroupingFlag = $this->hasParameter('taggroup');
            $onlyGroups = (array)$this->getParameter('group');
            $groupTagMapper = $this->getOwnerGroupTagMapper();

            $columns = false;
            if (!empty($onlyGroups)) {
                $columns = [
                    'groups' => [],
                    'operators' => [],
                ];
                foreach ($onlyGroups as $group) {
                    if (isset($groupTagMapper[$group])) {
                        if ($hasGroupingFlag) {
                            $columns['groups'][] = $group;
                        }else{
                            $columns['groups'] = array_merge($columns['groups'], $groupTagMapper[$group]);
                        }
                    } else {
                        if ($hasGroupingFlag){
                            $columns['groups'][] = $group;
                        }else{
                            $columns['operators'][] = $group;
                        }

                    }
                }
            }

            if (!$columns) {
                $ownerGroupFacetName = 'sensor_last_owner_group_id_i';
                if ($this->hasParameter('group') && !$hasGroupingFlag) {
                    $ownerGroupFacetName = 'sensor_last_owner_user_id_i';
                }
                $search = $this->repository->getStatisticsService()->searchPosts(
                    "{$categoryFilter}{$areaFilter}{$rangeFilter}{$ownerGroupFilter}{$typeFilter} limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000] pivot [facet=>[sensor_status_lk,{$ownerGroupFacetName}],mincount=>{$this->minCount}]",
                    ['authorFiscalCode' => $this->getAuthorFiscalCode()]
                );
                $pivotItems = isset($search->pivot) ? $search->pivot["sensor_status_lk,{$ownerGroupFacetName}"] : [];

                if ($hasGroupingFlag || !$this->hasParameter('group')){
                    $tree = $this->getGroupTree($hasGroupingFlag, $onlyGroups);
                }else{
                    $tree = $this->getOperatorsTree($onlyGroups);
                }

            }else{
                $pivotItems = [];
                $tree = [];
                if (!empty($columns['groups'])){
                    $ownerGroupFacetName = 'sensor_last_owner_group_id_i';
                    $search = $this->repository->getStatisticsService()->searchPosts(
                        "{$categoryFilter}{$areaFilter}{$rangeFilter}{$ownerGroupFilter}{$typeFilter} limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000] pivot [facet=>[sensor_status_lk,{$ownerGroupFacetName}],mincount=>{$this->minCount}]",
                        ['authorFiscalCode' => $this->getAuthorFiscalCode()]
                    );
                    $tempPivotItems = isset($search->pivot) ? $search->pivot["sensor_status_lk,{$ownerGroupFacetName}"] : [];
                    $pivotItems = array_merge($pivotItems, $tempPivotItems);
                    $treeTemp = $this->getGroupTree($hasGroupingFlag, $columns['groups']);
                    foreach ($treeTemp as $index => $item){
                        $tree[$index] = $item;
                    }
                }
                if (!empty($columns['operators'])){
                    $ownerGroupFacetName = 'sensor_last_owner_user_id_i';
                    $search = $this->repository->getStatisticsService()->searchPosts(
                        "{$categoryFilter}{$areaFilter}{$rangeFilter}{$ownerGroupFilter}{$typeFilter} limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000] pivot [facet=>[sensor_status_lk,{$ownerGroupFacetName}],mincount=>{$this->minCount}]",
                        ['authorFiscalCode' => $this->getAuthorFiscalCode()]
                    );
                    $tempPivotItems = isset($search->pivot) ? $search->pivot["sensor_status_lk,{$ownerGroupFacetName}"] : [];
                    $pivotItems = array_merge($pivotItems, $tempPivotItems);
                    $treeTemp = $this->getOperatorsTree($columns['operators']);
                    foreach ($treeTemp as $index => $item){
                        $tree[$index] = $item;
                    }
                }
            }

            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $serie = [];
            foreach ($tree as $treeId => $treeItem) {
                $serie[$treeId] = [
                    'interval' => $treeItem['name'],
                    'count' => 0
                ];
            }

            $series = $this->generateSeries($serie);

            $sorter = [];
            foreach ($pivotItems as $pivotItem) {
                $serieIndex = $pivotItem['value'] == 'open' ? 1 : 0;
                if (!isset($series[$serieIndex])){
                    continue;
                }
                foreach ($tree as $treeId => $treeItem) {
                    foreach ($pivotItem['pivot'] as $pivot) {
                        if ($pivot['value'] == $treeId || in_array($pivot['value'], $treeItem['children'])) {
                            $series[$serieIndex]['data'][$treeId]['count'] += $pivot['count'];
                            if (!isset($sorter['c_' . $treeId])){
                                $sorter['c_' . $treeId] = 0;
                            }
                            $sorter['c_' . $treeId] += $pivot['count'];
                        }
                    }
                }
            }

            arsort($sorter, SORT_NUMERIC);

            foreach (array_keys($sorter) as $cid){
                $id = substr($cid, 2);
                $this->data['intervals'][] = $tree[$id]['name'];
            }

            foreach ($series as $serieIndex => $serie){
                $data = [];
                foreach (array_keys($sorter) as $cid){
                    $id = substr($cid, 2);
                    $data[] = $serie['data'][$id];
                }
                $series[$serieIndex]['data'] = $data;
            }
            if ($this->addTotals) {
                $totals = [];
                foreach ($sorter as $cid => $count) {
                    $id = substr($cid, 2);
                    $totals[] = [
                        'interval' => $tree[$id]['name'],
                        'count' => $count
                    ];
                }
                $series[2] = [
                    'name' => 'Totale',
                    'data' => $totals,
                    'color' => $this->getColor('pareto'),
                    'id' => 2
                ];
            }

            $this->data['series'] = array_values($series);
        }

        return $this->data;
    }

    protected function generateSeries($serie)
    {
        return [
            0 => [
                'name' => 'Chiusa',
                'data' => $serie,
                'color' => $this->getColor('close'),
                'id' => 0
            ],
            1 => [
                'name' => 'Aperta',
                'data' => $serie,
                'color' => $this->getColor('open'),
                'id' => 1
            ],
        ];
    }

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [
            [
                'type' => 'pareto',
                'name' => 'Pareto',
                'yAxis' => 1,
                'zIndex' => 10,
                'baseSeries' => 3,
                'color' => $data['series'][2]['color'],
                'tooltip' => [
                    'valueDecimals' => 2,
                    'valueSuffix' => '%'
                ]
            ]
        ];
        foreach ($data['series'] as $serie){
            $item = [
                'name' => $serie['name'],
                'color' => isset($serie['color']) ? $serie['color'] : null,
                'type' => 'column',
                'yAxis' => 0,
                'zIndex' => 2,
                'visible' => $serie['name'] != 'Totale',
                'showInLegend' => $serie['name'] != 'Totale',
                'data' => []
            ];
            foreach ($serie['data'] as $datum){
                if ($datum['interval'] !== 'all'){
                    $item['data'][] = [
                        $datum['interval'],
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
                        'categories' => $data['intervals'],
                        'title' => [
                            'enabled' => false,
                        ],
                        'tickmarkPlacement' => 'on'
                    ],
                    'yAxis' => [
                        [
                            'min' => 0,
                            'title' => [
                                'text' => 'Numero'
                            ],
                            'alignTicks' => false,
                            'gridLineWidth' => 0,
                            'stackLabels' => [
                                'enabled' => true,
                                'style' => [
                                    'fontWeight' => 'bold',
                                    'color' => 'gray'
                                ]
                            ]
                        ],[
                            'title' => [
                                'text' => ''
                            ],
                            'minPadding' => 0,
                            'maxPadding' => 0,
                            'max' => 100,
                            'min' => 0,
                            'opposite' => true,
                            'alignTicks' => false,
                            'labels' => [
                                'format' => '{value}%'
                            ],
                        ]
                    ],
                    'tooltip' => [
                        'shared' => true,
                    ],
                    'plotOptions' => [
                        'column' => [
                            'stacking' => 'normal',
                            'dataLabels' => [
                                'enabled' => true,
                                'color' => 'white',
                                'style' => [
                                    'textShadow' => '0 0 3px black'
                                ]
                            ]
                        ],
                        'pareto' => [
                            'dataLabels' => [
                                'enabled' => true,
                                'format' => '{point.y:.1f}',
                            ]
                        ]
                    ],
                    'title' => [
                        'text' => $this->getDescription()
                    ],
                    'series' => $series
                ]
            ]
        ];
    }

    protected function getTableIntervalName()
    {
        return 'Gruppo';
    }
}
