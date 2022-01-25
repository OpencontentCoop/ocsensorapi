<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Legacy\Repository;

class Trend extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

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
        return 'trend';
    }

    public function getName()
    {
        return Translator::translate('Trend opening/closing', 'chart');
    }

    public function getDescription()
    {
        return Translator::translate('Number of issues open and closed by time interval', 'chart');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byStartInterval = $this->getIntervalFilter('sensor');
//            if ($this->hasParameter('group')) {
//                $byStartInterval = $this->getIntervalFilter('sensor_first_assignment');
//            }
            $byEndInterval = $this->getIntervalFilter('sensor_closing');
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getMainCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $rangeFilter = $this->getRangeFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();

            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter}{$typeFilter} limit 1 facets [raw[{$byStartInterval}]|alpha|10000,raw[{$byEndInterval}]|alpha|10000]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );

            $series = [
                0 => [
                    'name' => 'inserite',
                    'color' => $this->getColor('open'),
                    'data' => []
                ],
                1 => [
                    'name' => 'chiuse',
                    'color' => $this->getColor('close'),
                    'data' => []
                ]
            ];

            $facets = $search->facets;
            $intervals = [];
            foreach ($facets as $facet) {
                foreach (array_keys($facet['data']) as $value) {
                    if (!isset($intervals[$value])) {
                        $intervals[$value] = is_callable($intervalNameParser) ? (int)$intervalNameParser($value) : (int)$value;
                    }
                }
            }

            asort($intervals);
            foreach ($intervals as $intervalId => $intervalName) {
                $hasSerie = [];
                foreach ($facets as $index => $facet) {
                    foreach ($facet['data'] as $key => $value) {
                        if ($key == $intervalId){
                            $series[$index]['data'][] = [
                                'interval' => $intervalName,
                                'count' => $value
                            ];
                            $hasSerie[] = $index;
                        }
                    }
                }
                if (count($hasSerie) !== count($series)){
                    foreach (array_keys($series) as $index){
                        if (!in_array($index, $hasSerie)){
                            $series[$index]['data'][] = [
                                'interval' => $intervalName,
                                'count' => 0
                            ];
                        }
                    }
                }
            }
            $this->data['series'] = $series;
            $this->data['intervals'] = array_values($intervals);
            usort($this->data['series'], function ($a, $b) {
                return !strcmp($a["name"], $b["name"]);
            });
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
                'color' => isset($serie['color']) ? $serie['color'] : null,
                'type' => 'column',
                'data' => []
            ];
            foreach ($serie['data'] as $datum){
                if ($datum['interval'] !== 'all'){
                    $item['data'][] = [
                        $datum['interval'] * 1000,
                        $datum['count']
                    ];
                }
            }
            $series[] = $item;
        }
        return [[
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
                ],
                'tooltip' => [
                    'shared' => true,
                ],
                'plotOptions' => [
                    'column' => [
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
        ]];
    }
}
