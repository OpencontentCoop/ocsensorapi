<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use Opencontent\Sensor\Legacy\Utils\Translator;

class ClosingTrendPerGroup extends ClosingTrend
{
    public function getIdentifier()
    {
        return 'closing_trend_per_group';
    }

    public function getName()
    {
        return Translator::translate('Trend by group of operators in charge', 'chart');
    }

    public function getDescription()
    {
        return Translator::translate('Percentage of messages closed by group of operators in charge', 'chart');
    }

    public function getDataFields()
    {
        $fields = ['percentage_sf' => [
            'label' => 'Totale',
            'color' => $this->getColor('close')
        ]];
        $this->dailyReportRepository = new \SensorDailyReportRepository();
        $selectedGroups = [];
        if ($this->hasParameter('group')) {
            $selectedGroups = (array)$this->getParameter('group');
        }
        foreach ($this->dailyReportRepository->getGroups() as $id => $group) {
            if (!empty($selectedGroups) && !in_array($id, $selectedGroups)){
                continue;
            }
            $fields['percentage_group_'. $id .'_sf'] = [
                'label' => $group['name'],
                'color' => $this->getColor($id)
            ];
        }

        return $fields;
    }

    protected function getFilterLegend()
    {
        $groups = [];
        foreach ($this->dailyReportRepository->getGroups() as $index => $group){
            $groups[] = ['id' => $index, 'name' => $group['name']];
        }
        return ['groups' => $groups];
    }
}
