<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Api\StatisticFactory;

trait FiltersTrait
{
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
        $end = $this->hasParameter('end') ? $this->getParameter('end') : null;
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
                    $dateTime = date_create_from_format('Yz', $value);
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
                    $dateTime = \DateTime::createFromFormat('d m Y', "01 $month $year");
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
}