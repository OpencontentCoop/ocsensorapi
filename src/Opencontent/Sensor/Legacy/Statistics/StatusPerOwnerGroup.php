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

    protected $repository;

    protected $data;

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
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();

            //$ownerGroupFacetName = 'sensor_history_owner_id_lk';
            $ownerGroupFacetName = 'sensor_last_owner_group_id_i';
            if ($this->hasParameter('group')) {
                $ownerGroupFacetName = 'sensor_last_owner_user_id_i';
            }

            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter}{$typeFilter} limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000] pivot [facet=>[sensor_status_lk,{$ownerGroupFacetName}],mincount=>{$this->minCount}]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );

            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $pivotItems = isset($search->pivot) ? $search->pivot["sensor_status_lk,{$ownerGroupFacetName}"] : [];

            $tree = $this->hasParameter('group') ? $this->getOperatorsTree($this->getParameter('group')) : $this->getGroupTree();

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

    private function getGroupTree()
    {
        $tree = [];
        $groupTree = $this->repository->getGroupsTree();
        if ($this->hasParameter('taggroup')){
            $groupTagCounter = [];
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $groupTag = $groupTreeItem->attribute('group');
                if (empty($groupTag)) {
                    $tree[$groupTreeItem->attribute('id')] = [
                        'name' => $groupTreeItem->attribute('name'),
                        'children' => []
                    ];
                }else{
                    if (isset($groupTagCounter[$groupTag])){
                        $groupTagId = $groupTagCounter[$groupTag];
                    }else{
                        $groupTagId = $groupTagCounter[$groupTag] = count($groupTagCounter) + 1;
                    }

                    if (isset($tree[$groupTagId])){
                        $tree[$groupTagId]['children'][] = $groupTreeItem->attribute('id');
                    }else{
                        $tree[$groupTagId] = [
                            'name' => $groupTag,
                            'children' => [$groupTreeItem->attribute('id')]
                        ];
                    }
                }
            }
        }else {
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $tree[$groupTreeItem->attribute('id')] = [
                    'name' => $groupTreeItem->attribute('name'),
                    'children' => []
                ];
            }
        }

        return $tree;
    }

    private function getOperatorsTree($groupIdList)
    {
        $tree = [];
        foreach ($groupIdList as $groupId) {
            $group = $this->repository->getGroupService()->loadGroup($groupId);
            if ($group instanceof Group) {
                $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*');
                $operators = $operatorResult['items'];
                $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);

                foreach ($operators as $operator) {
                    $tree[$operator->attribute('id')] = [
                        'name' => $operator->attribute('name'),
                        'children' => []
                    ];
                }
            }
        }

        return $tree;
    }

    private function recursiveLoadOperatorsByGroup(Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next']);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
        }

        return $operators;
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
}