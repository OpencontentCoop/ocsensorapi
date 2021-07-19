<?php

namespace Opencontent\Sensor\Legacy\Statistics;

class OpenPerOwnerGroup extends StatusPerOwnerGroup
{
    protected $addTotals = false;

    public function getIdentifier()
    {
        return 'open_owner_groups';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Aperte per gruppo di incaricati');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Segnalazioni aperte per gruppi di incaricati coinvolti');
    }

    protected function generateSeries($serie)
    {
        return [
            1 => [
                'name' => 'Aperta',
                'data' => $serie,
                'color' => $this->getColor('open'),
                'id' => 1
            ],
        ];
    }

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        $series = [
            [
                'type' => 'pareto',
                'name' => 'Pareto',
                'yAxis' => 1,
                'zIndex' => 10,
                'baseSeries' => 1,
                'color' => '#333',
                'tooltip' => [
                    'valueDecimals' => 2,
                    'valueSuffix' => '%'
                ]
            ]
        ];
        foreach ($data['series'] as $serie){
            if ($serie['name'] == 'Aperta') {
                $item = [
                    'name' => $serie['name'],
                    'color' => $serie['color'],
                    'type' => 'column',
                    'yAxis' => 0,
                    'zIndex' => 2,
                    'visible' => $serie['name'] != 'Totale',
                    'showInLegend' => $serie['name'] != 'Totale',
                    'data' => []
                ];
                foreach ($serie['data'] as $datum) {
                    if ($datum['interval'] !== 'all') {
                        $item['data'][] = [
                            $datum['interval'],
                            $datum['count']
                        ];
                    }
                }
                $series[] = $item;
            }
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
                        'title' => [
                            'enabled' => false,
                        ],
                        'tickmarkPlacement' => 'on'
                    ],
                    'yAxis' => [
                        [
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
                        ],[
                            'title' => [
                                'text' => ''
                            ],
                            'minPadding' => 0,
                            'maxPadding' => 0,
                            'max' => 100,
                            'min' => 0,
                            'opposite' => true,
                            'alignTicks' => false,
                            'labels' => [
                                'format' => '{value}%'
                            ],
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
                        ],
                        'pareto' => [
                            'dataLabels' => [
                                'enabled' => true,
                                'format' => '{point.y:.1f}',
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
}