<?php


namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Exportable;
use OpenContent\Sensor\Api\Values\User;
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
        return new \ArrayIterator( $this->users );
    }

}