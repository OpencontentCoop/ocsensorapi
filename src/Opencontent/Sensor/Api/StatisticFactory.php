<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Exception\InvalidArgumentException;

abstract class StatisticFactory
{
    const DEFAULT_INTERVAL = 'yearly';

    protected $parameters;

    protected $authorFiscalCode;

    /**
     * @var Repository
     */
    protected $repository;

    protected $data;

    protected $renderSettings = [
        'engine' => 'highcharts',
        'highcharts' => ['use_highstock' => false]
    ];

    abstract public function getData();

    public function init()
    {
        $this->data = null;
        $this->parameters = [];

        return $this;
    }

    /**
     * @return mixed
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param mixed $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function getParameter($name)
    {
        return isset($this->parameters[$name]) && !empty($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function hasParameter($name)
    {
        return isset($this->parameters[$name]) && !empty($this->parameters[$name]);
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function hasAttribute($name)
    {
        return in_array($name, $this->attributes());
    }

    public function attributes()
    {
        return ['name', 'identifier', 'description', 'render_settings'];
    }

    public function attribute($name)
    {
        if ($name == 'name') {
            return $this->getName();
        }

        if ($name == 'identifier') {
            return $this->getIdentifier();
        }

        if ($name == 'description') {
            return $this->getDescription();
        }

        if ($name == 'render_settings') {
            return $this->renderSettings;
        }

        return null;
    }

    abstract public function getName();

    abstract public function getIdentifier();

    abstract public function getDescription();

    /**
     * @return string
     */
    public function getAuthorFiscalCode()
    {
        return $this->authorFiscalCode;
    }

    /**
     * @param string $authorFiscalCode
     */
    public function setAuthorFiscalCode($authorFiscalCode)
    {
        $this->authorFiscalCode = $authorFiscalCode;
    }

    protected function getColor($identifier)
    {
        if ($this->repository instanceof Repository
            && !$this->repository->getSensorSettings()->get('UseStatCalculatedColor')) {
            return false;
        }
        
        $color = false;
        switch ($identifier) {
            case 'open':
            case 'sensor_assign_fix_time_i':
                $color = '#f0ad4e';
                break;

            case 'pending':
            case 'sensor_open_read_time_i':
                $color = '#d9534f';
                break;

            case 'sensor_read_assign_time_i':
                $color = '#ffb791';
                break;

            case 'close':
            case 'sensor_fix_close_time_i':
                $color = '#5cb85c';
                break;

            case 'pareto';
                $color = '#333';
                break;
        }

        if (!$color){
            $colors = [
                "#7cb5ec", "#434348", "#90ed7d", "#e5b41f", "#8085e9", "#f15c80", "#e4d354", "#2b908f", "#f45b5b", "#91e8e1",
                '#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#92A8CD', '#A47D7C', '#B5CA92',
                '#2f7ed8', '#0d233a', '#8bbc21', '#910000', '#1aadce', '#492970', '#f28f43', '#77a1e5', '#c42525', '#a6c96a'
            ];

            $hash = strlen($identifier) > 10 ? substr(sha1($identifier), 0, 10) : $identifier;
            $colorIndex = hexdec($hash) % count($colors);
            $color = $colors[$colorIndex];
        }

        return $color;
    }

    public static function getAvailableFormats()
    {
        return [
            'default',
            'highcharts',
            'table',
            'data',
        ];
    }

    public function getDataByFormat($formatIdentifier)
    {
        if ($formatIdentifier == 'default'){
            return $this->getDefaultFormatData();
        }elseif ($formatIdentifier == 'highcharts'){
            return $this->getHighchartsformatData();
        }elseif ($formatIdentifier == 'table'){
            return $this->getTableformatData();
        }elseif ($formatIdentifier == 'queries'){
            return $this->getQueries();
        }elseif ($formatIdentifier == 'data'){
            return $this->getData();
        }

        throw new InvalidArgumentException("Stat $formatIdentifier not handled");
    }

    protected function getQueries()
    {
        $this->repository->getStatisticsService()->startCollectQueries();
        $this->getData();
        $this->repository->getStatisticsService()->stopCollectQueries();
        return $this->repository->getStatisticsService()->getCollectedQueries();
    }

    protected function getDefaultFormatData()
    {
        return [
            'identifier' => $this->getIdentifier(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'data' => $this->getData()
        ];
    }

    protected function getHighchartsFormatData()
    {
        return [];
    }

    protected function getTableFormatData()
    {
        $data = $this->getData();
        $all = [];
        $columns = array_column($data['series'], 'name');
        array_unshift($columns, $this->getTableIntervalName());
        $rows = [];
        foreach ($data['series'] as $i => $serie){
            foreach ($serie['data'] as $o => $datum){
                if ($datum['interval'] == 'all'){
                    $all[] = [$serie['name'], $datum[$this->getTableColumnField()]];
                }else {
                    if (!isset($rows[$o][0])) {
                        $rows[$o][] = $this->formatTableIntervalTimestamp($datum['interval']);
                    }
                    $rows[$o][] = $datum[$this->getTableColumnField()];
                }
            }

        }

        $table = [];
        if (!empty($all)){
            $table[] = $all;
        }
        $table[] = array_merge([$columns], $rows);

        return $table;
    }

    protected function getTableColumnField()
    {
        return 'count';
    }

    protected function getTableIntervalName()
    {
        return ['label' => 'Data', 'type' => 'date'];
    }

    protected function formatTableIntervalTimestamp($interval)
    {
        try {
            $date = \DateTime::createFromFormat('U', $interval);
            if ($date instanceof \DateTime) {
                return 'Date(' . $date->format('Y, m, d, H, i') . ')';
            }
            return $interval;
        } catch (\Exception $e) {
            return $interval;
        }
    }
}
