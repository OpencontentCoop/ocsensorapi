<?php

namespace Opencontent\Sensor\Core\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\ParticipantRole;

class CanSelectReceiverInPrivateMessage extends UserIs implements SettingPermissionInterface
{
    public $identifier = 'can_select_receiver_in_private_message';

    private $useDirectPrivateMessage;

    public function __construct($useDirectPrivateMessage)
    {
        $this->useDirectPrivateMessage = $useDirectPrivateMessage;
    }

    public function userHasPermission(User $user, Post $post)
    {
        return $this->useDirectPrivateMessage;
    }
}