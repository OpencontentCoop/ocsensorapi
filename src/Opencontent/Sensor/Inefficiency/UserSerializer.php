<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\User;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\User as UserStruct;

class UserSerializer
{
    public function serialize(User $user): UserStruct
    {
        if ($user->id === null) {
            $anonymous = eZUser::fetch(eZUser::anonymousId());
            $data = [
                'nome' => '?',
                'cognome' => '?',
                'cellulare' => '?',
                'telefono' => '?',
                'email' => strtolower($anonymous->attribute('email')),
                'codice_fiscale' => 'XXXXXX00X00X000X',
            ];
        } else {
            $data = [
                'nome' => $user->firstName,
                'cognome' => $user->lastName,
                'cellulare' => $user->phone,
                'telefono' => $user->phone,
                'email' => strtolower($user->email),
                'codice_fiscale' => strtoupper($user->fiscalCode),
            ];
        }

        return UserStruct::fromArray($data);
    }
}