<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;

class StatusPerCategory extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    protected $data;

    protected $minCount = 0;

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
        return 'status_categories';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Stato per categoria');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Stato delle segnalazioni per categoria');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getMainCategoryFilter();
            if ($this->hasParameter('maincategory')){
                $this->minCount = 1;
            }
            $rangeFilter = $this->getRangeFilter();
            $areaFilter = $this->getAreaFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();
            
            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter}{$typeFilter} limit 1 facets [raw[submeta_category___id____si]|alpha|1000] pivot [facet=>[sensor_status_lk,submeta_category___id____si],mincount=>{$this->minCount}]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $pivotItems = $search->pivot["sensor_status_lk,submeta_category___id____si"];

            $categoryTree = $this->repository->getCategoriesTree();
            $tree = [];
            foreach ($categoryTree->attribute('children') as $categoryTreeItem) {
                $tree[$categoryTreeItem->attribute('id')] = [
                    'name' => $categoryTreeItem->attribute('name'),
                    'children' => []
                ];
                foreach ($categoryTreeItem->attribute('children') as $categoryTreeItemChild) {
                    if ($this->hasParameter('maincategory')){
                        if ($categoryTreeItem->attribute('id') == $this->getParameter('maincategory')){
                            $tree[$categoryTreeItemChild->attribute('id')] = [
                                'name' => $categoryTreeItemChild->attribute('name'),
                                'children' => []
                            ];
                        }
                    }else {
                        $tree[$categoryTreeItem->attribute('id')]['children'][] = $categoryTreeItemChild->attribute('id');
                    }
                }
            }

            $serie = [];
            foreach ($tree as $treeId => $treeItem) {
                $serie[$treeId] = [
                    'interval' => $treeItem['name'],
                    'count' => 0
                ];
            }

            $series = [
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

            $sorter = [];
            foreach ($pivotItems as $pivotItem) {
                $serieIndex = $pivotItem['value'] == 'open' ? 1 : 0;
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
            $totals = [];
            foreach ($sorter as $cid => $count){
                $id = substr($cid, 2);
                $totals[] = [
                    'interval' => $tree[$id]['name'],
                    'count' => $count
                ];
            }
            $series[2] = [
                'name' => 'Totale',
                'data' => $totals,
                'id' => 2,
                'color' => $this->getColor('pareto'),
            ];

            $this->data['series'] = $series;
        }

        return $this->data;
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
                'color' => $serie['color'],
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
        return 'Categoria';
    }
}