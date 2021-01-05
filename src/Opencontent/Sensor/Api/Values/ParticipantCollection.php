<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Collection;
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
     * @return Participant|boolean
     */
    public function getParticipantById($id)
    {
        return isset($this->participants[$id]) ? $this->participants[$id] : false;
    }

    /**
     * @param $role
     *
     * @return ParticipantCollection
     */
    public function getParticipantsByRole($role)
    {
        $collection = new ParticipantCollection();
        foreach ($this->participants as $participant) {
            if ($participant->roleIdentifier == $role || $participant->roleName == $role) {
                $collection->addParticipant($participant);
            }
        }
        return $collection;
    }

    public function getParticipantIdList()
    {
        return array_keys($this->participants);
    }

    public function getParticipantIdListByType($type)
    {
        $list = [];
        foreach ($this->participants as $id => $participant){
            if ($participant->type == $type){
                $list[] = $participant->id;
            }
        }

        return $list;
    }

    public function getParticipantNameListByType($type)
    {
        $list = [];
        foreach ($this->participants as $participant){
            if ($participant->type == $type){
                $list[] = $participant->name;
            }
        }

        return $list;
    }

    public function getUserIdList()
    {
        $list = [];
        foreach ($this->participants as $participant) {
            $list = array_merge($list, array_keys($participant->users));
        }

        return array_unique($list);
    }

    /**
     * @param $id
     *
     * @return User|false
     */
    public function getUserById($id)
    {
        foreach ($this->participants as $participant) {
            $user = $participant->getUserById($id);
            if ($user)
                return $user;
        }
        return false;
    }

    /**
     * @param $userId
     *
     * @return Participant|false
     */
    public function getParticipantByUserId($userId)
    {
        foreach ($this->participants as $participant) {
            $user = $participant->getUserById($userId);
            if ($user)
                return $participant;
        }
        return false;
    }

    public function addParticipant(Participant $participant)
    {
        $this->participants[$participant->id] = $participant;
    }

    /**
     * @param Participant[] $participants
     */
    public function addParticipants($participants)
    {
        foreach ($participants as $participant)
            $this->addParticipant($participant);
    }

    protected function toArray()
    {
        return (array)$this->participants;
    }

    protected function fromArray(array $data)
    {
        $this->participants = $data;
    }

    public function jsonSerialize()
    {
        return self::toJson(array_values($this->participants));
    }
}