<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;

class PerArea extends StatisticFactory
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
        return 'areas';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Zone');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Numero di segnalazioni per zona');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $search = $this->repository->getStatisticsService()->searchPosts("{$categoryFilter}{$areaFilter} limit 1 facets [raw[submeta_area___id____si]|alpha|100] pivot [facet=>[submeta_area___id____si,{$byInterval}],mincount=>1]");

            $this->data = [
                'intervals' => [],
                'series' => [],
            ];
            $pivotItems = $search->pivot["submeta_area___id____si,{$byInterval}"];
            foreach ($pivotItems as $pivotItem) {
                $item = [
                    'name' => trim($this->repository->getAreaService()->loadArea((int)$pivotItem['value'])->name),
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
                $this->data['series'][] = $item;
            }
            $this->data['intervals'] = array_values($this->data['intervals']);
            usort($this->data['series'], function ($a, $b) {
                return strcmp($a["name"], $b["name"]);
            });
        }

        return $this->data;
    }
}