<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;
use Opencontent\Sensor\Legacy\Utils\Translator;

class PostAging extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;
    use BucketsTrait;

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
        return Translator::translate('Aging of issues', 'chart');
    }

    public function getDescription()
    {
        return Translator::translate('Number of issues by aging time buckets', 'chart');
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
            $tree[0] = 'Nessun gruppo incaricato';
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
                    'color' => $this->getColor($groupId),
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
                        $datum['interval'],
                        $datum['count'] == 0 ? null : $datum['count']
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
                    'categories' => $data['intervals'],
                    'tickmarkPlacement' => 'on',
                    'title' => [
                        'enabled' => false
                    ],
                ],
                'yAxis' => [
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
                    'text' => $this->getDescription()
                ],
                'legend' => [
                    'alignColumns' => false
                ],
                'series' => $series
            ]
        ]];
    }

    protected function getTableIntervalName()
    {
        return 'Intervallo';
    }
}
