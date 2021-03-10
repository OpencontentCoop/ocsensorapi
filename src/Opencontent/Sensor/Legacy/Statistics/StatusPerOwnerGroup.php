<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;

class StatusPerOwnerGroup extends StatisticFactory
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
        $ownerGroupFacetName = 'sensor_history_owner_id_lk';
        //$ownerGroupFacetName = 'sensor_last_owner_group_id_i';
        if ($this->data === null) {
            $categoryFilter = $this->getCategoryFilter();
            $rangeFilter = $this->getRangeFilter();
            $areaFilter = $this->getAreaFilter();
            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter} limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000] pivot [facet=>[sensor_status_lk,{$ownerGroupFacetName}],mincount=>{$this->minCount}]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );

            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $pivotItems = $search->pivot["sensor_status_lk,{$ownerGroupFacetName}"];

            $groupTree = $this->repository->getGroupsTree();
            $tree = [];
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $tree[$groupTreeItem->attribute('id')] = [
                    'name' => $groupTreeItem->attribute('name')
                ];
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
                        if ($pivot['value'] == $treeId) {
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