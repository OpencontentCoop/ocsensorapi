<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\User;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\User as UserStruct;

class PusherUserSerializer extends UserSerializer
{
    public function serialize(User $user): UserStruct
    {
        $data = parent::serialize($user);
        $data->email = $data->codice_fiscale . '-dev@example.com';
        return $data;
    }

}