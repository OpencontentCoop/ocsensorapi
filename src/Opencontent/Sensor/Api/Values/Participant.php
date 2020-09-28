<?php


namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\User;
use DateTime;

/**
 * Class Participant
 * @package Opencontent\Sensor\Api\Values
 */
class Participant extends Exportable implements \IteratorAggregate
{
    const TYPE_USER = 'user';

    const TYPE_GROUP = 'group';

    const TYPE_REMOVED = 'removed';

    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $roleIdentifier;

    /**
     * @var string
     */
    public $roleName;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var DateTime
     */
    public $lastAccessDateTime;

    /**
     * @var User[]
     */
    public $users = array();

    /**
     * @var string
     */
    public $type;

    public function getUserById($id)
    {
        return isset($this->users[$id]) ? $this->users[$id] : false;
    }

    public function addUser(User $user)
    {
        $this->users[$user->id] = $user;
    }

    public function getIterator()
    {
        return new \ArrayIterator((array)$this->users);
    }

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['users']);

        return self::toJson($objectVars);
    }

}