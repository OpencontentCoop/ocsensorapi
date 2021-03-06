<?php


namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\User;
use DateTime;

class Participant extends Exportable implements \IteratorAggregate
{
    public $id;

    public $roleIdentifier;

    public $roleName;

    public $name;

    /**
     * @var DateTime
     */
    public $lastAccessDateTime;

    /**
     * @var User[]
     */
    public $users;

    public function getUserById( $id )
    {
        return isset( $this->users[$id] ) ? $this->users[$id] : false;
    }

    public function addUser( User $user )
    {
        $this->users[$user->id] = $user;
    }

    public function getIterator()
    {
        return new \ArrayIterator( (array)$this->users );
    }

}