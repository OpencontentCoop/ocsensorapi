<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use ezpI18n;
use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;

class Users extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    /**
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'users';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Adesioni');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Andamento nuove adesioni');
    }

    public function getData()
    {
        if ($this->data === null) {
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $byInterval = $this->getIntervalFilter('creation');
            $rangeFilter = $this->getRangeFilter('published');

            $intervalNameParser = $this->getIntervalNameParser();
            $userSubtreeString = $this->repository->getUserService()->getSubtreeAsString();
            $search = $this->search(
                "{$rangeFilter} classes [user] and subtree [{$userSubtreeString}] limit 1 facets [raw[{$byInterval}]|alpha|100] pivot [facet=>[{$byInterval}],mincount=>1]"
            );

            $series = [];
            $pivotItems = $search->pivot["{$byInterval}"];
            $data = [];
            $total = 0;
            $intervalNames = [];
            $lastInterval = false;
            foreach ($pivotItems as $pivotItem) {
                $intervalName = is_callable($intervalNameParser) ? $intervalNameParser($pivotItem['value']) : $pivotItem['value'];
                $data[] = [
                    'interval' => $intervalName,
                    'count' => (int)$pivotItem['count'],
                ];
                $this->data['intervals'][] = $intervalName;
            }

            $this->data['series'][] = [
                'name' => 'Nuove adesioni',
                'data' => array_values($data),
            ];
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

    private function search($query)
    {
        $contentSearch = new ContentSearch();
        $contentSearch->setCurrentEnvironmentSettings(new \DefaultEnvironmentSettings());

        return $contentSearch->search($query, array());
    }

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [];
        foreach ($data['series'] as $serie){
            $item = [
                'name' => $this->getDescription(),
                'data' => []
            ];
            foreach ($serie['data'] as $datum){
                $item['data'][] = [
                    $datum['interval'] * 1000,
                    $datum['count']
                ];
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
                        'type' => 'datetime',
                        'ordinal' => false
                    ],
                    'yAxis' => [
                        'allowDecimals' => false,
                        'min' => 0,
                        'title' => [
                            'text' => 'Numero'
                        ]
                    ],
                    'plotOptions' => [
                        'column' => [
                            'dataLabels' => [
                                'enabled' => true,
                            ]
                        ],
                        'series' => [
                            'marker' => [
                                'enabled' => true,
                            ]
                        ]
                    ],
                    'title' => [
                        'text' => $this->getDescription()
                    ],
                    'legend' => [
                        'enabled' => false,
                    ],
                    'series' => $series
                ]
            ]
        ];
    }
}