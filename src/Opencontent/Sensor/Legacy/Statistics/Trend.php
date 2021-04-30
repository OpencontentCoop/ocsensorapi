<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;


class Trend extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

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
        return 'trend';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Trend inserimento/chiusura');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Numero di segnalazioni inserite e chiuse per intervallo di tempo');
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

            $search = $this->repository->getStatisticsService()->searchPosts(
                "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter} limit 1 facets [raw[{$byStartInterval}],raw[{$byEndInterval}]|alpha|100]",
                ['authorFiscalCode' => $this->getAuthorFiscalCode()]
            );
            $series = [
                0 => [
//                    'name' => $this->hasParameter('group') ? 'assegnate' : 'aperte',
                    'name' => 'inserite',
                    'data' => []
                ],
                1 => [
                    'name' => 'chiuse',
                    'data' => []
                ]
            ];

            $facets = $search->facets;
            $intervals = [];
            foreach ($facets as $facet) {
                foreach (array_keys($facet['data']) as $value) {
                    if (!isset($intervals[$value])) {
                        $intervals[$value] = is_callable($intervalNameParser) ? $intervalNameParser($value) : $value;
                    }
                }
            }
            ksort($intervals);

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
                return strcmp($a["name"], $b["name"]);
            });
        }

        return $this->data;
    }
}