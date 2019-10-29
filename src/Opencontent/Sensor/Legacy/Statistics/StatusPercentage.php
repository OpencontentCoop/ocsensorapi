<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use ezpI18n;
use Opencontent\Sensor\Legacy\Repository;

class StatusPercentage extends StatisticFactory
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
        return 'status';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Stato');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Numero totale e stato corrente');
    }

    public function getData()
    {
        if ($this->data === null) {
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $search = $this->repository->getStatisticsService()->searchPosts("{$categoryFilter}{$areaFilter} facets [status] limit 1");
            $data = [];
            $total = $search->totalCount;
            foreach ($search->facets[0]['data'] as $status => $count) {
                $data[] = [
                    'status' => $this->repository->getSensorPostStates('sensor')['sensor.' . $status]->attribute('current_translation')->attribute('name'),
                    'percentage' => floatval(number_format($count * 100 / $total, 2)),
                    'count' => $count
                ];
            }
            $this->data['intervals'] = [];
            $this->data['series'] = $data;
        }

        return $this->data;
    }

}