<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;

class TypePerCategory extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    protected $data;

    protected $minCount = 0;

    /**
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'type_categories';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Tipologia per categoria');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Tipologia delle segnalazioni per categoria');
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
            
            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter} limit 1 facets [raw[submeta_category___id____si]|alpha|1000] pivot [facet=>[attr_type_s,submeta_category___id____si],mincount=>{$this->minCount}]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $pivotItems = $search->pivot["attr_type_s,submeta_category___id____si"];

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

            foreach ($this->repository->getPostTypeService()->loadPostTypes() as $postType){
                $series[$postType->identifier] = [
                    'name' => $postType->name,
                    'data' => $serie,
                    'id' => $postType->identifier
                ];
            }


            $sorter = [];
            foreach ($pivotItems as $pivotItem) {
                $serieIndex = $pivotItem['value'];
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
            $series[] = [
                'name' => 'Totale',
                'data' => $totals,
                'id' => 2
            ];

            $this->data['series'] = array_values($series);
        }

        return $this->data;
    }

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [];
        foreach ($data['series'] as $serie){
            $item = [
                'name' => $serie['name'],
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
                        'tickmarkPlacement' => 'on',
                        'title' => [
                            'enabled' => false,
                        ],
                    ],
                    'yAxis' => [
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