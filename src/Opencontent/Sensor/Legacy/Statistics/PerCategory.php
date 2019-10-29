<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;

class PerCategory extends StatisticFactory
{
    use FiltersTrait;

    protected $repository;

    private $data;

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
        return ezpI18n::tr('sensor/chart', 'Numero di segnalazioni per categoria');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getCategoryFilter();

            $areaFilter = $this->getAreaFilter();
            $search = $this->repository->getStatisticsService()->searchPosts("{$categoryFilter}{$areaFilter} limit 1 facets [raw[submeta_category___id____si]|alpha|100] pivot [facet=>[submeta_category___id____si,{$byInterval}],mincount=>1]");

            $this->data = [
                'intervals' => [],
                'series' => [],
            ];
            $series = [];
            $pivotItems = $search->pivot["submeta_category___id____si,{$byInterval}"];
            foreach ($pivotItems as $pivotItem) {
                $item = [
                    'name' => (int)$pivotItem['value'],
                    'data' => []
                ];
                $item['data'][] = [
                    'interval' => 'all',
                    'count' => $pivotItem['count']
                ];
                $this->data['intervals']['all'] = 'all';
                foreach ($pivotItem['pivot'] as $value) {
                    $intervalName = is_callable($intervalNameParser) ? $intervalNameParser($value['value']) : $value['value'];
                    $item['data'][] = [
                        'interval' => $intervalName,
                        'count' => $value['count']
                    ];
                    $this->data['intervals'][$value['value']] = $intervalName;
                }
                $series[$pivotItem['value']] = $item;
            }
            $this->data['intervals'] = array_values($this->data['intervals']);
            sort($this->data['intervals']);

            $categoryTree = $this->repository->getCategoriesTree();
            foreach ($categoryTree->attribute('children') as $categoryTreeItem){
                if (isset($series[$categoryTreeItem->attribute('id')])) {
                    $item = $series[$categoryTreeItem->attribute('id')];
                    $item['name'] = $categoryTreeItem->attribute('name');
                    $item['id'] = $categoryTreeItem->attribute('id');
                    $item['series'] = [];
                    foreach ($categoryTreeItem->attribute('children') as $categoryTreeItemChild) {
                        if (isset($series[$categoryTreeItemChild->attribute('id')])) {
                            $child = $series[$categoryTreeItemChild->attribute('id')];
                            $child['name'] = $categoryTreeItemChild->attribute('name');
                            $child['id'] = $categoryTreeItemChild->attribute('id');
                            $item['series'][] = $child;
                        }
                    }
                    $this->data['series'][] = $item;
                }
            }
        }

        return $this->data;
    }
}