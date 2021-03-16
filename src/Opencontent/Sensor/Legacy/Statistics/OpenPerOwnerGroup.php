<?php

namespace Opencontent\Sensor\Legacy\Statistics;

class OpenPerOwnerGroup extends StatusPerOwnerGroup
{
    protected $addTotals = false;

    public function getIdentifier()
    {
        return 'open_owner_groups';
    }

    public function getName()
    {
        return \ezpI18n::tr('sensor/chart', 'Aperte per gruppo di incaricati');
    }

    public function getDescription()
    {
        return \ezpI18n::tr('sensor/chart', 'Segnalazioni aperte per gruppi di incaricati coinvolti');
    }

    protected function generateSeries($serie)
    {
        return [
            1 => [
                'name' => 'Aperta',
                'data' => $serie,
                'id' => 1
            ],
        ];
    }
}