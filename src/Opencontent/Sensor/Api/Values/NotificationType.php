<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

class NotificationType extends Exportable
{
    public $identifier;

    public $name;

    public $description;

    public $group = 'standard';

    public $template;

    public $targets = [
        ParticipantRole::ROLE_OBSERVER => [Participant::TYPE_USER],
        ParticipantRole::ROLE_OWNER => [Participant::TYPE_USER, Participant::TYPE_GROUP],
        ParticipantRole::ROLE_APPROVER => [Participant::TYPE_USER],
        ParticipantRole::ROLE_AUTHOR => [Participant::TYPE_USER],
    ];
}