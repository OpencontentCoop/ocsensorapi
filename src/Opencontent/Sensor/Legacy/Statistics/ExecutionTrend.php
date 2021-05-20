<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\QueryLanguage\Parser\Exception;
use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils;

class ExecutionTrend extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    protected $data;

    protected $minCount = 0;

    /**
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'execution_aging';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Tempi di lavorazione');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Numero di segnalazioni per tempi di lavorazione');
    }

    public function getData()
    {
        if ($this->data === null) {
            $ownerGroupFacetName = 'sensor_last_owner_group_id_i';
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            $typeFilter = $this->getTypeFilter();

            $groupIdlist = [];
            $data = [];
//            $queries = [];
            foreach ($this->getBuckets() as $bucket) {
                $rangeFilter = $bucket['filter'];
                $query = "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter}{$typeFilter} raw[sensor_workflow_status_lk] in [fixed,closed] limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000]";
//                $queries[$bucket['name']] = $query;
                try {
                    $search = $this->repository->getStatisticsService()->searchPosts(
                        $query,
                        ['authorFiscalCode' => $this->getAuthorFiscalCode()]
                    );
                    $data[$bucket['name']] = $search->facets[0]['data'];
                    $groupIdlist = array_merge(array_keys($search->facets[0]['data']), $groupIdlist);
                } catch (\Exception $exception) {
//                    $queries[$bucket['name']] .= ' ' . $exception->getMessage();
                    \eZDebug::writeError($exception->getMessage(), __METHOD__);
                }
            }

            $groupIdlist = array_unique($groupIdlist);
            sort($groupIdlist);

            $groupTree = $this->repository->getGroupsTree();
            $tree = [];
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $tree[$groupTreeItem->attribute('id')] = $groupTreeItem->attribute('name');
            }
            usort($groupIdlist, function ($a, $b) use ($tree) {
                return strcmp($tree[$a], $tree[$b]);
            });

            $series = [];
            foreach ($groupIdlist as $groupId) {
                $serieData = [];
                foreach ($this->getBuckets() as $bucket) {
                    $count = isset($data[$bucket['name']][$groupId]) ? (int)$data[$bucket['name']][$groupId] : 0;
                    $serieData[] = [
                        'interval' => $bucket['name'],
                        'count' => $count
                    ];
                }
                $series[] = [
                    'name' => $tree[$groupId],
                    'data' => $serieData,
                    'id' => $groupId
                ];
            }

            $intervals = [];
            foreach ($this->getBuckets() as $bucket) {
                $intervals[] = $bucket['name'];
            }

            $this->data = [
                'intervals' => $intervals,
                'series' => $series,
            ];
        }

        return $this->data;
    }

    protected function getBuckets($field = 'raw[sensor_assign_fix_time_i]')
    {
        $data = [];

        $startTimeInSeconds = '*';
        $endTimeInSeconds = 8 * 60 * 60;
        $item = [
            'name' => '8h',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 8 * 60 * 60;
        $endTimeInSeconds = 24 * 60 * 60;
        $item = [
            'name' => '8h-1g',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 24 * 60 * 60;
        $endTimeInSeconds = 3 * 24 * 60 * 60;
        $item = [
            'name' => '1-3gg',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 3 * 24 * 60 * 60;
        $endTimeInSeconds = 7 * 24 * 60 * 60;
        $item = [
            'name' => '3-7gg',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 7 * 24 * 60 * 60;
        $endTimeInSeconds = 15 * 24 * 60 * 60;
        $item = [
            'name' => '7-15gg',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 15 * 24 * 60 * 60;
        $endTimeInSeconds = 30 * 24 * 60 * 60;
        $item = [
            'name' => '15-30gg',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 30 * 24 * 60 * 60;
        $endTimeInSeconds = 90 * 24 * 60 * 60;
        $item = [
            'name' => '30-90gg',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        $startTimeInSeconds = 90 * 24 * 60 * 60;
        $endTimeInSeconds = '*';
        $item = [
            'name' => 'oltre 90gg',
            'filter' => " $field range [$startTimeInSeconds,$endTimeInSeconds] and ",
        ];
        $data[] = $item;

        return $data;
    }
}