<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Legacy\Repository;
use SensorDailySearchParameters;
use OCCustomSearchableRepositoryProvider;

class ClosingTrend extends StatisticFactory
{
    use FiltersTrait;

    /**
     * @var \SensorDailyReportRepository
     */
    protected $dailyReportRepository;

    /**
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
        $this->renderSettings['highcharts']['use_highstock'] = true;
    }

    public function getIdentifier()
    {
        return 'closing_trend';
    }

    public function getName()
    {
        return Translator::translate('Trend by category', 'chart');
    }

    public function getDescription()
    {
        return Translator::translate('Percentage of issues closed by category', 'chart');
    }

    public function getDataFields()
    {
        $this->dailyReportRepository = new \SensorDailyReportRepository();
        $fields = ['percentage_sf' => [
            'label' => 'Totale',
            'color' => $this->getColor('close')
        ]];
        $selectedCategories = [];
        if ($this->hasParameter('category')) {
            $selectedCategories = (array)$this->getParameter('category');
        }
        if ($this->hasParameter('maincategories')) {
            $selectedCategories = array_merge($selectedCategories, (array)$this->getParameter('maincategories'));
        }
        $selectedCategories = array_unique($selectedCategories);

        foreach ($this->dailyReportRepository->getCategories() as $id => $category) {
            if (!empty($selectedCategories) && in_array($id, $selectedCategories)) {
                $fields['percentage_cat_' . $id . '_sf'] = [
                    'label' => $category['name'],
                    'color' => $this->getColor($id)
                ];
            }
        }

        return $fields;
    }

    public function getData()
    {
        $fields = $this->getDataFields();

        $parameters = (new SensorDailySearchParameters())
            ->setStats(['field' => array_keys($fields), 'facet' => ['timestamp_i']])
            ->setLimit(0);

        $data = OCCustomSearchableRepositoryProvider::instance()
            ->provideRepository('sensor_daily_report')
            ->find($parameters);

        $stats = $data['stats']['stats_fields'];

        $series = [];
        foreach ($fields as $percentageField => $values) {
            $percentages = $this->getPercentages($stats, $percentageField);
            if ($percentages) {
                $series[] = [
                    'name' => $values['label'],
                    'data' => $percentages,
                    'color' => $values['color'],
                    'visible' => $percentageField === 'percentage_sf' || $this->hasParameter('maincategories') || $this->hasParameter('category') || $this->hasParameter('group'),
                ];
            }
        }

        return [
            'series' => $series
        ];
    }

    protected function getPercentages($stats, $percentageField)
    {
        if ($stats[$percentageField]['sum'] == 0) return false;

        $percentages = [];
        foreach ($stats[$percentageField]['facets']['timestamp_i'] as $timestamp => $values) {
            $percentages[] = [
                $timestamp*1000,
                (float)number_format($values['mean'], 0)
            ];
        }

        usort($percentages, function ($a, $b){
            if ($a[0] == $b[0]) {
                return 0;
            }
            return ($a[0] < $b[0]) ? -1 : 1;
        });

        return $percentages;
    }

    protected function getHighchartsFormatData()
    {
        $data = $this->getData();
        return [
            [
                'type' => 'stockChart',
                'config' => [
                    'tooltip' => [
                        'pointFormat' => '<span style="color:{series.color}">{series.name}</span>: <b>{point.y}%</b><br/>',
                        'valueDecimals' => 0
                    ],
                    'rangeSelector' => [
                        'selected' => 5,
                        'buttons' => [[
                            'type' => 'week',
                            'count' => 1,
                            'text' => '1w',
                            'title' => '1 settimana'
                        ],[
                            'type' => 'month',
                            'count' => 1,
                            'text' => '1m',
                            'title' => '1 mese'
                        ], [
                            'type' => 'month',
                            'count' => 3,
                            'text' => '3m',
                            'title' => '3 mesi'
                        ], [
                            'type' => 'month',
                            'count' => 6,
                            'text' => '6m',
                            'title' => '6 mesi'
                        ], [
                            'type' => 'year',
                            'count' => 1,
                            'text' => '1a',
                            'title' => '1 anno'
                        ], [
                            'type' => 'all',
                            'text' => 'Tutto',
                            'title' => 'Tutto'
                        ]]
                    ],
                    'plotOptions' => [
                        'line' => [
                            'dataLabels' => [
                                'enabled' => true,
                                'color' => 'black'
                            ]
                        ],
                        'series' => [
                            'showInNavigator' => true
                        ]
                    ],
                    'legend' => [
                        'enabled' => true,
                        'alignColumns' => false
                    ],
                    'title' => [
                        'text' => $this->getDescription()
                    ],
                    'yAxis' => [
                        'max' => 100,
                        'min' => 0,

                        'plotLines' => [[
                            'value' => 0,
                            'width' => 2,
                            'color' => 'silver'
                        ]]
                    ],
                    'scrollbar' => [
                        'enabled' => false
                    ],
                    'series' => $data['series']
                ],
                'filterLegend' => $this->getFilterLegend(),
            ]
        ];
    }

    protected function getFilterLegend()
    {
        $categories = [];
        foreach ($this->dailyReportRepository->getCategories() as $index => $category){
            $categories[] = ['id' => $index, 'name' => $category['name']];
        }
        return ['categories' => $categories];
    }

    protected function getTableFormatData()
    {
        $data = $this->getData();

        $columns = array_column($data['series'], 'name');
        array_unshift($columns, $this->getTableIntervalName());
        $rows = [];
        foreach ($data['series'] as $i => $serie){
            foreach ($serie['data'] as $o => $datum){
                if (!isset($rows[$o][0])){
                    $rows[$o][] = $this->formatTableIntervalTimestamp($datum[0]);
                }
                $rows[$o][] = $datum[1];
            }

        }
        return [array_merge($columns, $rows)];
    }
}
