<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Event;

interface EventService
{
    public function fire(Event $event);
}