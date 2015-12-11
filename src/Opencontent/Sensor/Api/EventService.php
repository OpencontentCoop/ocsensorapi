<?php

namespace OpenContent\Sensor\Api;


interface EventService
{
    public function fire( $event );
}