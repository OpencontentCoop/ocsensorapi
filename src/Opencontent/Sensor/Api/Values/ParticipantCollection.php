<?php

namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Collection;
use Traversable;

class ParticipantCollection extends Collection
{

    /**
     * @var Participant[]
     */
    public $participants = array();

    /**
     * @param $id
     *
     * @return Participant
     */
    public function getParticipantById( $id )
    {
        return isset( $this->participants[$id] ) ? $this->participants[$id] : false;
    }

    /**
     * @param $role
     *
     * @return ParticipantCollection
     */
    public function getParticipantsByRole( $role )
    {
        $collection = new ParticipantCollection();
        foreach( $this->participants as $participant ){
            if ($participant->roleIdentifier == $role || $participant->roleName == $role){
                $collection->addParticipant( $participant );
            }
        }
        return $collection;
    }

    public function getParticipantIdList()
    {
        return array_keys( $this->participants );
    }

    /**
     * @param $id
     *
     * @return User
     */
    public function getUserById( $id )
    {
        foreach( $this->participants as $participant )
        {
            $user = $participant->getUserById( $id );
            if ( $user )
                return $user;
        }
        return false;
    }

    public function addParticipant( Participant $participant )
    {
        $this->participants[$participant->id] = $participant;
    }

    /**
     * @param Participant[] $participants
     */
    public function addParticipants( $participants ){
        foreach( $participants as $participant )
            $this->addParticipant( $participant );
    }

    protected function toArray()
    {
        return (array) $this->participants;
    }

    protected function fromArray( array $data )
    {
        $this->participants = $data;
    }
}