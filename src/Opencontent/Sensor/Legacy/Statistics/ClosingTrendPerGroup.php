<?php

namespace Opencontent\Sensor\Legacy\Statistics;

use ezpI18n;

class ClosingTrendPerGroup extends ClosingTrend
{
    public function getIdentifier()
    {
        return 'closing_trend_per_group';
    }

    public function getName()
    {
        return ezpI18n::tr('sensor/chart', 'Trend per gruppo di incaricati');
    }

    public function getDescription()
    {
        return ezpI18n::tr('sensor/chart', 'Percentuale di segnalazioni chiuse per gruppo di incaricati');
    }

    public function getDataFields()
    {
        $fields = ['percentage_sf' => [
            'label' => 'Totale',
            'color' => $this->getColor('close')
        ]];
        $repo = new \SensorDailyReportRepository();
        $selectedGroups = [];
        if ($this->hasParameter('group')) {
            $selectedGroups = (array)$this->getParameter('group');
        }
        foreach ($repo->getGroups() as $id => $group) {
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
}