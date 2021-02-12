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
            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter} limit 1 facets [raw[submeta_category___id____si]|alpha|100] pivot [facet=>[sensor_status_lk,submeta_category___id____si],mincount=>{$this->minCount}}]",
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
                $this->data['intervals'][] = $categoryTreeItem->attribute('name');
                $tree[$categoryTreeItem->attribute('id')] = [
                    'name' => $categoryTreeItem->attribute('name'),
                    'children' => []
                ];
                foreach ($categoryTreeItem->attribute('children') as $categoryTreeItemChild) {
                    $tree[$categoryTreeItem->attribute('id')]['children'][] = $categoryTreeItemChild->attribute('id');
                }
            }

            foreach ($pivotItems as $pivotItem) {
                $name = $pivotItem['value'] == 'open' ? 'Aperta' : 'Chiusa';
                $data = [];
                foreach ($tree as $treeId => $treeItem) {
                    $item = [
                        'interval' => $treeItem['name'],
                        'count' => 0
                    ];
                    foreach ($pivotItem['pivot'] as $pivot) {
                        if ($pivot['value'] == $treeId || in_array($pivot['value'], $treeItem['children'])) {
                            $item['count'] += $pivot['count'];
                        }
                    }
                    $data[] = $item;
                }
                $this->data['series'][] = [
                    'name' => $name,
                    'data' => $data,
                    'id' => $name == 'open' ? 1 : 0
                ];
            }

            return $this->data;
        }
    }
}