<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Collection;
use Opencontent\Sensor\Api\Values\Permission;

class PermissionCollection extends Collection
{
    /**
     * @var Permission[]
     */
    protected $permissions = array();

    public function hasPermission($identifier)
    {
        return isset($this->permissions[$identifier]) ? $this->permissions[$identifier] : false;
    }

    public function addPermission(Permission $permission)
    {
        $this->permissions[$permission->identifier] = $permission->grant;
    }

    protected function toArray()
    {
        return (array)$this->permissions;
    }

    protected function fromArray(array $data)
    {
        $this->permissions = $data;
    }

    public function jsonSerialize()
    {
        $data = [];
        foreach ($this->permissions as $identifier => $grant) {
            $permission = new Permission();
            $permission->identifier = $identifier;
            $permission->grant = $grant;
            $data[] = $permission->jsonSerialize();
        }

        return $data;
    }


}
