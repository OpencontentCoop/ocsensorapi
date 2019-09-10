<?php

namespace Opencontent\Sensor\Legacy;

use League\Event\EmitterAwareInterface;
use League\Event\EmitterAwareTrait;
use Opencontent\Sensor\Api\Values\Event;
use Opencontent\Sensor\Core\EventService as BaseEventService;
use League\Event\Event as EmissibileEvent;

class EventService extends BaseEventService implements EmitterAwareInterface
{
    use EmitterAwareTrait;

    public function fire(Event $event)
    {
        $emissibileEvent = new EmissibileEvent($event->identifier);
        $this->getEmitter()->emit($emissibileEvent, $event);
    }

}