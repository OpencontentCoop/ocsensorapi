<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;
use Opencontent\Sensor\Api\Values\Scenario\ScenarioCriterion;

class Scenario extends Exportable
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var array
     */
    public $triggers = [];

    /**
     * @var ScenarioCriterion[]
     */
    public $criteria = [];

    /**
     * @var integer[]
     */
    protected $approversIdList = [];

    /**
     * @var integer[]
     */
    protected $ownersIdList = [];

    /**
     * @var integer[]
     */
    protected $ownerGroupsIdList = [];

    /**
     * @var integer[]
     */
    protected $observersIdList = [];

    protected $expiry;

    /**
     * @var integer
     */
    protected $category;

    /**
     * @var Post
     */
    protected $currentPost;

    protected $currentContext = [];

    public function setCurrentPost(Post $post)
    {
        $this->currentPost = $post;
    }

    public function setCurrentContext(array $context)
    {
        $this->currentContext = $context;
    }

    /**
     * @return integer[]
     */
    public function getApprovers()
    {
        return (array)$this->approversIdList;
    }

    /**
     * @return integer[]
     */
    public function getOwnerGroups()
    {
        return (array)$this->ownerGroupsIdList;
    }

    /**
     * @return integer[]
     */
    public function getOwners()
    {
        return (array)$this->ownersIdList;
    }

    /**
     * @return integer[]
     */
    public function getObservers()
    {
        return (array)$this->observersIdList;
    }

    /**
     * @return bool
     */
    public function hasApprovers()
    {
        return count($this->getApprovers()) > 0;
    }

    /**
     * @return bool
     */
    public function hasOwnerGroups()
    {
        return count($this->getOwnerGroups()) > 0;
    }

    /**
     * @return bool
     */
    public function hasOwners()
    {
        return count($this->getOwners()) > 0;
    }

    /**
     * @return bool
     */
    public function hasObservers()
    {
        return count($this->getObservers()) > 0;
    }

    /**
     * @return integer
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @return bool
     */
    public function hasCategory()
    {
        return intval($this->category) > 0;
    }

    /**
     * @return integer
     */
    public function getExpiry()
    {
        return (int)$this->expiry;
    }

    public function jsonSerialize()
    {
        $objectVars['id'] = $this->id;
        $objectVars['triggers'] = $this->triggers;
        $criteria = [];
        foreach ($this->criteria as $criterion){
            $criteria[$criterion->getIdentifier()] = $criterion;
        }
        $objectVars['criteria'] = $criteria;
        $objectVars['assignments'] = [
            'approver' => $this->getApprovers(),
            'owner_group' => $this->getOwnerGroups(),
            'owner' => $this->getOwners(),
            'observer' => $this->getObservers(),
            'category' => $this->getCategory(),
        ];
        $objectVars['expiry'] = $this->getExpiry() > 0 ? $this->getExpiry() : null;

        return $this->toJson($objectVars);
    }

    public function getApplicationMessage($trigger)
    {
        return 'Applied scenario #' . $this->id;
    }
}