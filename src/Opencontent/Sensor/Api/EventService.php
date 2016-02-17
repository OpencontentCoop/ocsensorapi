<?php

namespace Opencontent\Sensor\Api;


interface EventService
{
    public function fire( $event );
}