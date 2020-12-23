<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use ezpI18n;
use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Legacy\Repository;

class Users extends StatisticFactory
{
    use FiltersTrait;
    use AccessControlTrait;

    protected $repository;

    private $data;

    /**
     * @param Repository $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    public function getIdentifier()
    {
        return 'users';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Adesioni');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Andamento nuove adesioni');
    }

    protected function getIntervalNameParser()
    {
        $interval = $this->hasParameter('interval') ? $this->getParameter('interval') : 'yearly';
        $intervalNameParser = false;
        $format = 'U';
        switch ($interval) {
            case 'daily':
                $intervalNameParser = function ($value) use ($format) {
                    $dateTime = date_create_from_format('Yz', $value);
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'monthly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $month = substr($value, -2);
                    $dateTime = \DateTime::createFromFormat('d m Y', "01 $month $year");
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'quarterly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $part = substr($value, -1);
                    $month = $part == 1 ? '01' : $part == 2 ? '04' : $part == 3 ? '07' : '10';
                    $dateTime = date_create_from_format('d/m/Y', "01/$month/$year");
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'half-yearly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $part = substr($value, -1);
                    $month = $part == 2 ? '07' : '01';
                    $dateTime = date_create_from_format('d/m/Y', "01/$month/$year");
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'yearly':
                $intervalNameParser = function ($value) use ($format) {
                    $dateTime = date_create_from_format('d/m/Y', "01/01/$value");
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

        }

        return $intervalNameParser;
    }

    public function getData()
    {
        if ($this->data === null) {
            $this->data = [
                'intervals' => [],
                'series' => [],
            ];

            $byInterval = $this->getIntervalFilter('creation');
            $intervalNameParser = $this->getIntervalNameParser();
            $userSubtreeString = $this->repository->getUserService()->getSubtreeAsString();
            $search = $this->search(
                "classes [user] and subtree [{$userSubtreeString}] limit 1 facets [raw[{$byInterval}]|alpha|100] pivot [facet=>[{$byInterval}],mincount=>1]"
            );

            $series = [];
            $pivotItems = $search->pivot["{$byInterval}"];
            $data = [];
            $total = 0;
            $intervalNames = [];
            $lastInterval = false;
            foreach ($pivotItems as $pivotItem) {
                $intervalName = is_callable($intervalNameParser) ? $intervalNameParser($pivotItem['value']) : $pivotItem['value'];
                $data[] = [
                    'interval' => $intervalName,
                    'count' => (int)$pivotItem['count'],
                ];
                $this->data['intervals'][] = $intervalName;
            }

            $this->data['series'][] = [
                'name' => 'Nuove adesioni',
                'data' => array_values($data),
            ];
        }

        return $this->data;
    }

    private function search($query)
    {
        $contentSearch = new ContentSearch();
        $contentSearch->setCurrentEnvironmentSettings(new \DefaultEnvironmentSettings());

        return $contentSearch->search($query, array());
    }
}