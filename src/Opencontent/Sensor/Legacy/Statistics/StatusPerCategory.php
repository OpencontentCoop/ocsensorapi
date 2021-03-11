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
            $categoryFilter = $this->getCategoryFilter();
            $rangeFilter = $this->getRangeFilter();
            $areaFilter = $this->getAreaFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            
            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter} limit 1 facets [raw[submeta_category___id____si]|alpha|1000] pivot [facet=>[sensor_status_lk,submeta_category___id____si],mincount=>{$this->minCount}]",
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
                    $tree[$categoryTreeItem->attribute('id')]['children'][] = $categoryTreeItemChild->attribute('id');
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
                    'id' => 0
                ],
                1 => [
                    'name' => 'Aperta',
                    'data' => $serie,
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
                'id' => 2
            ];

            $this->data['series'] = $series;
        }

        return $this->data;
    }
}