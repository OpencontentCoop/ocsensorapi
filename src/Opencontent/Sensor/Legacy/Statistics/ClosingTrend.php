<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;
use SensorDailySearchParameters;
use OCCustomSearchableRepositoryProvider;

class ClosingTrend extends StatisticFactory
{
    use FiltersTrait;

    protected $repository;

    private $data;

    protected $renderSettings = [
        'use_highstock' => true
    ];

    /**
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'closing_trend';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Trend per categoria');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Percentuale di segnalazioni chiuse per categoria');
    }

    public function getDataFields()
    {
        $repo = new \SensorDailyReportRepository();
        $fields = ['percentage_sf' => [
            'label' => 'Totale',
            'color' => $this->getColor('close')
        ]];
        foreach ($repo->getCategories() as $id => $category) {
            $fields['percentage_cat_'. $id .'_sf'] = [
                'label' => $category['name'],
                'color' => $this->getColor($id)
            ];
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
                    'visible' => $percentageField === 'percentage_sf',
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
}