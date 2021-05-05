<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils;

class PostAging extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    protected $data;

    protected $minCount = 0;

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
        return 'post_aging';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Segnalazioni aperte');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Segnalazioni aperte per periodo');
    }

    public function getData()
    {
        if ($this->data === null) {

            $ownerGroupFacetName = 'sensor_last_owner_group_id_i';
            $categoryFilter = $this->getCategoryFilter();
            $areaFilter = $this->getAreaFilter();
            $groupFilter = $this->getOwnerGroupFilter();
            
            $groupIdlist = [];
            $data = [];
            foreach ($this->getBuckets() as $bucket) {
                $rangeFilter = $bucket['filter'];
                $search = $this->repository->getStatisticsService()->searchPosts(
                    "{$categoryFilter}{$areaFilter}{$rangeFilter}{$groupFilter} raw[sensor_status_lk] = 'open' limit 1 facets [raw[{$ownerGroupFacetName}]|alpha|10000]",
                    ['authorFiscalCode' => $this->getAuthorFiscalCode()]
                );
                $data[$bucket['name']] = $search->facets[0]['data'];
                $groupIdlist = array_merge(array_keys($search->facets[0]['data']), $groupIdlist);
            }

            $groupIdlist = array_unique($groupIdlist);
            sort($groupIdlist);

            $groupTree = $this->repository->getGroupsTree();
            $tree = [];
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $tree[$groupTreeItem->attribute('id')] = $groupTreeItem->attribute('name');
            }
            usort($groupIdlist, function ($a, $b) use ($tree){
                return strcmp($tree[$a], $tree[$b]);
            });

            $series = [];
            foreach ($groupIdlist as $groupId){
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

    private function getBuckets()
    {
        $data = [];
        $field = 'published';

        $now = new \DateTime('now', Utils::getDateTimeZone());
        $start = (clone $now)->sub(new \DateInterval('PT8H'));
        $item = [
            'name' => '8h',
            'filter' => " $field range [{$start->format('c')},*] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P1D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P3D'))->setTime(23, 59);
        $item = [
            'name' => '1-3gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P3D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P7D'))->setTime(23, 59);
        $item = [
            'name' => '3-7gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P7D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P15D'))->setTime(23, 59);
        $item = [
            'name' => '7-15gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P15D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P30D'))->setTime(23, 59);
        $item = [
            'name' => '15-30gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P30D'))->setTime(23, 59);
        $start = (clone $now)->sub(new \DateInterval('P90D'))->setTime(23, 59);
        $item = [
            'name' => '30-90gg',
            'filter' => " $field range [{$start->format('c')},{$end->format('c')}] and ",
        ];
        $data[] = $item;

        $end = (clone $now)->sub(new \DateInterval('P90D'))->setTime(23, 59);
        $item = [
            'name' => 'oltre 90gg',
            'filter' => " $field range [*,{$end->format('c')}] and ",
        ];
        $data[] = $item;

        return $data;
    }
}