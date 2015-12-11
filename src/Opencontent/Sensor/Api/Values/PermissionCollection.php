<?php

namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Collection;
use OpenContent\Sensor\Api\Values\Permission;

class PermissionCollection extends Collection implements \IteratorAggregate
{
    /**
     * @var Permission[]
     */
    protected $permissions = array();

    public function hasPermission( $identifier )
    {
        return isset( $this->permissions[$identifier] ) ? $this->permissions[$identifier] : false;
    }

    public function addPermission( Permission $permission )
    {
        $this->permissions[$permission->identifier] = $permission->grant;
    }

    protected function toArray()
    {
        return (array) $this->permissions;
    }

    protected function fromArray( array $data )
    {
        $this->permissions = $data;
    }
}
