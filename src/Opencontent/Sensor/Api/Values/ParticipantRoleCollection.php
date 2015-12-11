<?php

namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Collection;

class ParticipantRoleCollection extends Collection
{
    public $roles = array();

    protected function toIterator()
    {
        return $this->roles;
    }

    /**
     * @param $id
     *
     * @return ParticipantRole|false
     */
    public function getParticipantRoleById( $id )
    {
        return isset( $this->roles[$id] ) ? $this->roles[$id] : false;
    }

    public function addParticipantRole( ParticipantRole $role )
    {
        $this->roles[$role->id] = $role;
    }

    protected function toArray()
    {
        return (array) $this->roles;
    }

    protected function fromArray( array $data )
    {
        $this->roles = $data;
    }
}