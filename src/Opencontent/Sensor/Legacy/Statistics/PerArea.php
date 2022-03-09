<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Legacy\Repository;

class PerArea extends StatisticFactory
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
        return 'areas';
    }

    public function getName()
    {
        return Translator::translate( 'Area', 'chart');
    }

    public function getDescription()
    {
        return Translator::translate('Number of issues by area', 'chart');
    }

    public function getData()
    {
        if ($this->data === null) {
            $byInterval = $this->getIntervalFilter();
            $intervalNameParser = $this->getIntervalNameParser();
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $rangeFilter = $this->getRangeFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();
            $userGroupFilter = $this->getUserGroupFilter();

            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter}{$typeFilter}{$userGroupFilter} limit 1 facets [raw[submeta_area___id____si]|alpha|1000] pivot [facet=>[submeta_area___id____si,{$byInterval}],mincount=>1]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );

            $this->data = [
                'intervals' => [],
                'series' => [],
            ];
            $pivotItems = $search->pivot["submeta_area___id____si,{$byInterval}"];
            foreach ($pivotItems as $pivotItem) {
                $item = [
                    'name' => trim($this->repository->getAreaService()->loadArea((int)$pivotItem['value'])->name),
                    'color' => $this->getColor($pivotItem['value']),
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

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [];
        $pieSeries = [];
        foreach ($data['series'] as $serie){
            $item = [
                'name' => $serie['name'],
                'color' => isset($serie['color']) ? $serie['color'] : null,
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
                        'color' => isset($serie['color']) ? $serie['color'] : null,
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
