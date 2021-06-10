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
        $fields = ['percentage_sf' => 'Totale'];
        $repo = new \SensorDailyReportRepository();
        foreach ($repo->getGroups() as $id => $group) {
            $fields['percentage_group_'. $id .'_sf'] = $group['name'];
        }

        return $fields;
    }
}