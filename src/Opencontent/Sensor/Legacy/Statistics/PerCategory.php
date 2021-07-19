<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;

class PerCategory extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    protected $data;

    protected $minCount = 1;

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
        return 'categories';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Categorie');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Numero di segnalazioni aperte per categoria');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getCategoryFilter();
            $rangeFilter = $this->getRangeFilter();
            $areaFilter = $this->getAreaFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();
            
            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter}{$typeFilter} limit 1 facets [raw[submeta_category___id____si]|alpha|100] pivot [facet=>[submeta_category___id____si,{$byInterval}],mincount=>{$this->minCount}]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];
            $dataForCount = [];
            $series = [];
            $pivotItems = $search->pivot["submeta_category___id____si,{$byInterval}"];
            foreach ($pivotItems as $pivotItem) {
                $item = [
                    'name' => (int)$pivotItem['value'],
                    'color' => $this->getColor($pivotItem['value']),
                    'data' => []
                ];
                $item['data'][] = [
                    'interval' => 'all',
                    'count' => $pivotItem['count']
                ];
                $this->data['intervals']['all'] = 'all';
                $dataForCount[$item['name']]['all'] = $pivotItem['count'];
                foreach ($pivotItem['pivot'] as $value) {
                    $intervalName = is_callable($intervalNameParser) ? $intervalNameParser($value['value']) : $value['value'];
                    $item['data'][] = [
                        'interval' => $intervalName,
                        'count' => $value['count']
                    ];
                    $dataForCount[$item['name']][$intervalName] = $value['count'];
                    $this->data['intervals'][$value['value']] = $intervalName;
                }
                $series[$pivotItem['value']] = $item;
            }

            foreach ($this->data['intervals'] as $identifier => $intervalName) {
                foreach ($series as $id => $serie) {
                    $intervals = array_column($serie['data'], 'interval');
                    if (!in_array($intervalName, $intervals)){
                        $series[$id]['data'][] = [
                            'interval' => $intervalName,
                            'count' => 0
                        ];
                    }
                }
            }

            $this->data['intervals'] = array_values($this->data['intervals']);
            sort($this->data['intervals']);

            $selectedCategory = [];
            if ($this->hasParameter('category')) {
                $selectedCategory = $this->getParameter('category');
                if (!empty($selectedCategory) && !is_array($selectedCategory)) {
                    $selectedCategory = [$selectedCategory];
                }
            }

            $categoryTree = $this->repository->getCategoriesTree();
            foreach ($categoryTree->attribute('children') as $categoryTreeItem){
                if (isset($series[$categoryTreeItem->attribute('id')])) {
                    $item = $series[$categoryTreeItem->attribute('id')];
                }else {
                    $item = ['data' => []];
                    foreach ($this->data['intervals'] as $interval) {
                        $item['data'][] = [
                            'interval' => $interval,
                            'count' => 0
                        ];
                    }
                }
                $item['name'] = $categoryTreeItem->attribute('name');
                $item['id'] = $categoryTreeItem->attribute('id');
                $item['series'] = [];

                $recountTotal = [];
                foreach ($categoryTreeItem->attribute('children') as $categoryTreeItemChild) {
                    if (isset($series[$categoryTreeItemChild->attribute('id')])) {
                        $child = $series[$categoryTreeItemChild->attribute('id')];
                        $child['name'] = $categoryTreeItemChild->attribute('name');
                        $child['id'] = $categoryTreeItemChild->attribute('id');
                        $recountTotal[] = $child['id'];
                        if (in_array($item['id'], $selectedCategory) || in_array($child['id'], $selectedCategory)){
                            $child['series'] = [];
                            $this->data['series'][] = $child;
                        }
                    }elseif (in_array($item['id'], $selectedCategory) || in_array($categoryTreeItemChild->attribute('id'), $selectedCategory)){
                        $child = ['data' => []];
                        foreach ($this->data['intervals'] as $interval) {
                            $child['data'][] = [
                                'interval' => $interval,
                                'count' => 0
                            ];
                        }
                        $child['name'] = $categoryTreeItemChild->attribute('name');
                        $child['id'] = $categoryTreeItemChild->attribute('id');
                        $child['series'] = [];
                        $this->data['series'][] = $child;
                    }
                }

                if (empty($selectedCategory)) {
                    foreach ($item['data'] as $index => $datum) {
                        foreach ($recountTotal as $childId) {
                            if (isset($dataForCount[$childId][$datum['interval']])) {
                                $item['data'][$index]['count'] += $dataForCount[$childId][$datum['interval']];
                            }
                        }
                    }
                }

                $hasContent = array_sum(array_column($item['data'], 'count')) > 0;

                if ($hasContent) {
                    $this->data['series'][] = $item;
                }
            }
        }

        sort($this->data['intervals']);
        foreach ($this->data['series'] as $index => $serie){
            usort($this->data['series'][$index]['data'], function ($a, $b){
                if ($a['interval'] == $b['interval']) {
                    return 0;
                }
                return ($a['interval'] < $b['interval']) ? -1 : 1;
            });
        }

        return $this->data;
    }

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [];
        $pieSeries = [];
        foreach ($data['series'] as $serie){
            $item = [
                'name' => $serie['name'],
                'color' => $serie['color'],
                'data' => []
            ];
            foreach ($serie['data'] as $datum){
                if ($datum['interval'] !== 'all'){
                    $item['data'][] = [
                        $datum['interval'] * 1000,
                        $datum['count']
                    ];
                }else{
                    $pieSeries[] = [
                        'name' => $serie['name'],
                        'color' => $serie['color'],
                        'y' => $datum['count']
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
                        'plotBackgroundColor' => null,
                        'plotBorderWidth' => null,
                        'plotShadow' => false,
                        'type' => 'pie'
                    ],
                    'title' => [
                        'text' => $this->getDescription(),
                    ],
                    'accessibility' => [
                        'point' => [
                            'valueSuffix' => '%'
                        ]
                    ],
                    'tooltip' => [
                        'pointFormat' => '{series.name}: <b>{point.percentage:.1f}%</b>'
                    ],
                    'plotOptions' => [
                        'pie' => [
                            'allowPointSelect' => true,
                            'cursor' => 'pointer',
                            'dataLabels' => [
                                'enabled' => true,
                                'format' => '<b>{point.name}:</b> {point.y} - {point.percentage:.1f}%'
                            ]
                        ]
                    ],
                    'series' => [[
                        'name' => $this->getName(),
                        'colorByPoint' => true,
                        'data' => $pieSeries
                    ]],
                ]
            ],
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
                        'allowDecimals' => false,
                        'min' => 0,
                        'title' => [
                            'text' => 'Numero'
                        ],
                        'stackLabels' => [
                            'enabled' => true,
                            'style' => [
                            'fontWeight' => 'bold',
                                'color' => 'gray'
                            ]
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
                        ]
                    ],
                    'title' => [
                        'text' => ''
                    ],
                    'series' => $series
                ]
            ]
        ];
    }
}