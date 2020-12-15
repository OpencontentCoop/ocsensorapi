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

    protected function getIntervalFilter()
    {
        $interval = $this->hasParameter('interval') ? $this->getParameter('interval') : StatisticFactory::DEFAULT_INTERVAL;
        $intervalNameParser = false;
        switch ($interval) {
            case 'monthly':
                $byInterval = 'sensor_month_i';
                break;

            case 'quarterly':
                $byInterval = 'sensor_quarter_i';
                break;

            case 'half-yearly':
                $byInterval = 'sensor_semester_i';
                break;

            case 'yearly':
                $byInterval = 'sensor_year_i';
                break;

            default:
                $byInterval = 'sensor_year_i';
        }

        return $byInterval;
    }

    protected function getIntervalNameParser()
    {
        $interval = $this->hasParameter('interval') ? $this->getParameter('interval') : 'yearly';
        $intervalNameParser = false;
        switch ($interval) {
            case 'monthly':
                $intervalNameParser = function ($value) {
                    $year = substr($value, 0, 4);
                    $month = substr($value, -2);
                    return "{$year} {$month}";
                };
                break;

            case 'quarterly':
                $intervalNameParser = function ($value) {
                    $year = substr($value, 0, 4);
                    $part = substr($value, -1);

                    return "{$year} {$part}°";
                };
                break;

            case 'half-yearly':
                $intervalNameParser = function ($value) {
                    $year = substr($value, 0, 4);
                    $part = substr($value, -1);

                    return "{$year} {$part}°";
                };
                break;

            case 'yearly':
                $intervalNameParser = function ($value) {
                    return $value;
                };
                break;

        }

        return $intervalNameParser;
    }

    protected function getAuthorFiscalCodeParameter()
    {
        return $this->hasParameter('authorFiscalCode') ? $this->getParameter('authorFiscalCode') : false;
    }
}