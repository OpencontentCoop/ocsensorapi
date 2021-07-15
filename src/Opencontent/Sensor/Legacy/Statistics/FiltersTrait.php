<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Legacy\SearchService;
use Opencontent\Sensor\Legacy\Utils;

trait FiltersTrait
{
    /**
     * @var Repository
     */
    protected $repository;

    protected function getCategoryFilter()
    {
        $categoryFilter = '';
        if ($this->hasParameter('category')) {
            $category = $this->getParameter('category');
            $categoryTree = $this->repository->getCategoriesTree();
            if (!empty($category)){
                if (!is_array($category)){
                    $category = [$category];
                }
                $children = [];
                foreach ($category as $categoryId){
                    foreach ($categoryTree->attribute('children') as $categoryTreeItem){
                        if ($categoryTreeItem->attribute('id') == $categoryId){
                            foreach ($categoryTreeItem->attribute('children') as $child){
                                $children[] = $child->attribute('id');
                            }
                        }
                    }
                }
                $category = array_merge($category, $children);
                $categoryFilter = 'raw[submeta_category___id____si] in [' . implode(',', (array)$category) . '] and ';
            }
        }

        return $categoryFilter;
    }

    protected function getMainCategoryFilter()
    {
        $categoryFilter = '';
        if ($this->hasParameter('maincategory')) {
            $categoryId = (int)$this->getParameter('maincategory');
            $categoryTree = $this->repository->getCategoriesTree();
            if ($categoryId > 0){
                $categoryList = [$categoryId];
                foreach ($categoryTree->attribute('children') as $categoryTreeItem){
                    if ($categoryTreeItem->attribute('id') == $categoryId){
                        foreach ($categoryTreeItem->attribute('children') as $child){
                            $categoryList[] = $child->attribute('id');
                        }
                    }
                }
                $categoryFilter = 'raw[submeta_category___id____si] in [' . implode(',', (array)$categoryList) . '] and ';
            }
        }

        return $categoryFilter;
    }

    protected function getAreaFilter()
    {
        $areaFilter = '';
        if ($this->hasParameter('area')) {
            $area = $this->getParameter('area');
            if (!empty($area)){
                if (!is_array($area)){
                    $area = [$area];
                }
                $areaFilter = 'raw[submeta_area___id____si] in [' . implode(',', $area) . '] and ';
            }
        }

        return $areaFilter;
    }

    protected function getIntervalFilter($prefix = 'sensor')
    {
        $interval = $this->hasParameter('interval') ? $this->getParameter('interval') : StatisticFactory::DEFAULT_INTERVAL;
        $intervalNameParser = false;
        switch ($interval) {
            case 'daily':
                $byInterval = $prefix . '_day_i';
                break;

            case 'weekly':
                $byInterval = $prefix . '_week_i';
                break;

            case 'monthly':
                $byInterval = $prefix . '_month_i';
                break;

            case 'quarterly':
                $byInterval = $prefix . '_quarter_i';
                break;

            case 'half-yearly':
                $byInterval = $prefix . '_semester_i';
                break;

            case 'yearly':
                $byInterval = $prefix . '_year_i';
                break;

            default:
                $byInterval = $prefix . '_year_i';
        }

        return $byInterval;
    }

    protected function getRangeFilter($field = null)
    {
        if (!$field){
            $field = $this->hasParameter('event') ? $this->getParameter('event') : 'open';
        }

        $start = $this->hasParameter('start') ? $this->getParameter('start') : null;
        if ($field !== 'published' && $start && $start != '*') {
            $time = new \DateTime($start, new \DateTimeZone('UTC'));

            if (!$time instanceof \DateTime) {
                throw new \Exception("Problem with date $start");
            }
            $start = '"' . \ezfSolrDocumentFieldBase::convertTimestampToDate($time->format('U')) . '"';
        }

        $end = $this->hasParameter('end') ? $this->getParameter('end') : null;
        if ($field !== 'published' && $end && $end != '*') {
            $time = new \DateTime($end, new \DateTimeZone('UTC'));

            if (!$time instanceof \DateTime) {
                throw new \Exception("Problem with date $end");
            }
            $end = '"' . \ezfSolrDocumentFieldBase::convertTimestampToDate($time->format('U')) . '"';
        }

        if ($start && $end && $field){
            return " $field range [$start,$end] and ";
        }

        return '';
    }

    protected function getIntervalNameParser()
    {
        $interval = $this->hasParameter('interval') ? $this->getParameter('interval') : 'yearly';
        $intervalNameParser = false;
        $format = 'U';
        switch ($interval) {
            case 'daily':
                $intervalNameParser = function ($value) use ($format) {
                    $dateTime = date_create_from_format('Yz', $value, Utils::getDateTimeZone());
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'weekly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $week = substr($value, 4);
                    $dateTime = new \DateTime();
                    $dateTime->setISODate($year,$week);
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'monthly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $month = substr($value, -2);
                    $dateTime = \DateTime::createFromFormat('d m Y', "01 $month $year", Utils::getDateTimeZone());
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'quarterly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $part = substr($value, -1);
                    if ($part == 1) $month = '01';
                    elseif ($part == 2) $month = '04';
                    elseif ($part == 3) $month = '07';
                    else $month = '10';
                    $dateTime = date_create_from_format('d/m/Y', "01/$month/$year", Utils::getDateTimeZone());
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'half-yearly':
                $intervalNameParser = function ($value) use ($format) {
                    $year = substr($value, 0, 4);
                    $part = substr($value, -1);
                    $month = $part == 2 ? '07' : '01';
                    $dateTime = date_create_from_format('d/m/Y', "01/$month/$year", Utils::getDateTimeZone());
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

            case 'yearly':
                $intervalNameParser = function ($value) use ($format) {
                    $dateTime = date_create_from_format('d/m/Y', "01/01/$value", Utils::getDateTimeZone());
                    return $dateTime instanceof \DateTime ? $dateTime->setTime(0,0)->format($format) : $value;
                };
                break;

        }

        return $intervalNameParser;
    }

    protected function getOwnerGroupFilter()
    {
        $groupFilter = '';
        if ($this->hasParameter('group')) {
            $group = $this->getParameter('group');
            if (!empty($group)){
                if (!is_array($group)){
                    $group = [$group];
                }
                $groupFilter = 'raw[sensor_last_owner_group_id_i] in [' . implode(',', $group) . '] and ';
            }
        }

        return $groupFilter;
    }

    protected function getTypeFilter()
    {
        $typeFilter = '';
        if ($this->hasParameter('type')) {
            $type = $this->getParameter('type');
            if (!empty($type)){
                if (!is_array($type)){
                    $type = [$type];
                }
                $typeFilter = 'raw[sensor_type_s] in [' . implode(',', $type) . '] and ';
            }
        }

        return $typeFilter;
    }

    protected function getGroupTree($groupedByTag = false)
    {
        $tree = [];
        $tree[0] = [
            'name' => 'Nessun gruppo incaricato',
            'children' => []
        ];
        $groupTree = $this->repository->getGroupsTree();
        if ($groupedByTag){
            $groupTagCounter = [];
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $groupTag = $groupTreeItem->attribute('group');
                if (empty($groupTag)) {
                    $tree[$groupTreeItem->attribute('id')] = [
                        'name' => $groupTreeItem->attribute('name'),
                        'children' => []
                    ];
                }else{
                    if (isset($groupTagCounter[$groupTag])){
                        $groupTagId = $groupTagCounter[$groupTag];
                    }else{
                        $groupTagId = $groupTagCounter[$groupTag] = count($groupTagCounter) + 1;
                    }

                    if (isset($tree[$groupTagId])){
                        $tree[$groupTagId]['children'][] = $groupTreeItem->attribute('id');
                    }else{
                        $tree[$groupTagId] = [
                            'name' => $groupTag,
                            'children' => [$groupTreeItem->attribute('id')]
                        ];
                    }
                }
            }
        }else {
            foreach ($groupTree->attribute('children') as $groupTreeItem) {
                $tree[$groupTreeItem->attribute('id')] = [
                    'name' => $groupTreeItem->attribute('name'),
                    'children' => []
                ];
            }
        }

        return $tree;
    }

    protected function getOperatorsTree($groupIdList)
    {
        $tree = [];
        $tree[0] = [
            'name' => 'Nessun operatore incaricato',
            'children' => []
        ];
        foreach ($groupIdList as $groupId) {
            $group = $this->repository->getGroupService()->loadGroup($groupId);
            if ($group instanceof Group) {
                $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, '*');
                $operators = $operatorResult['items'];
                $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);

                foreach ($operators as $operator) {
                    $tree[$operator->attribute('id')] = [
                        'name' => $operator->attribute('name'),
                        'children' => []
                    ];
                }
            }
        }

        return $tree;
    }

    private function recursiveLoadOperatorsByGroup(Group $group, $operatorResult, &$operators)
    {
        if ($operatorResult['next']) {
            $operatorResult = $this->repository->getOperatorService()->loadOperatorsByGroup($group, SearchService::MAX_LIMIT, $operatorResult['next']);
            $operators = array_merge($operatorResult['items'], $operators);
            $this->recursiveLoadOperatorsByGroup($group, $operatorResult, $operators);
        }

        return $operators;
    }

    protected function getGapFilter()
    {
        $interval = $this->hasParameter('interval') ? $this->getParameter('interval') : StatisticFactory::DEFAULT_INTERVAL;
        $intervalNameParser = false;
        switch ($interval) {
            case 'daily':
                $byInterval = '1DAY';
                break;

            case 'weekly':
                $byInterval = '7DAY';
                break;

            case 'monthly':
                $byInterval = '1MONTH';
                break;

            case 'quarterly':
                $byInterval = '3MONTH';
                break;

            case 'half-yearly':
                $byInterval = '6MONTH';
                break;

            case 'yearly':
                $byInterval = '1YEAR';
                break;

            default:
                $byInterval = '1YEAR';
        }

        return $byInterval;
    }
}