<?php

namespace Opencontent\Sensor\Legacy\Statistics;

class StatusPerCategory extends PerCategory
{
    public function getIdentifier()
    {
        return 'status_categories';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Stato per categorie');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Stato delle segnalazioni per categoria');
    }

    protected function getIntervalFilter($prefix = 'sensor')
    {
        return 'sensor_status_lk';
    }

    protected function getIntervalNameParser()
    {
        return null;
    }
}