<?php

namespace Opencontent\Sensor\Core;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\PermissionService as PermissionServiceInterface;
use Opencontent\Sensor\Api\Values\Permission;
use Opencontent\Sensor\Api\Values\PermissionCollection;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Exception\BaseException;

class PermissionService implements PermissionServiceInterface
{

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var PermissionDefinition[]
     */
    protected $permissionDefinitions;

    private $userPermissions = [];

    /**
     * @param Repository $repository
     * @param PermissionDefinition[] $permissionDefinitions
     */
    public function __construct(Repository $repository, $permissionDefinitions)
    {
        $this->repository = $repository;
        $this->permissionDefinitions = $permissionDefinitions;
    }

    public function loadPermissionDefinitionByIdentifier($identifier)
    {
        foreach ($this->permissionDefinitions as $permissionDefinition) {
            if ($permissionDefinition->identifier == $identifier)
                return $permissionDefinition;
        }
        throw new BaseException("Permission $identifier not found");
    }

    public function loadUserPostPermissionCollection(User $user, Post $post)
    {        
        if (!isset($this->userPermissions[$user->id][$post->id])) {
            $permissionCollection = new PermissionCollection();
            foreach ($this->permissionDefinitions as $permissionDefinition) {
                $permission = new Permission();
                $permission->identifier = $permissionDefinition->identifier;
                $permission->grant = (bool)$permissionDefinition->userHasPermission($user, $post);
                $permissionCollection->addPermission($permission);
            }
            $this->userPermissions[$user->id][$post->id] = $permissionCollection;
        }

        return $this->userPermissions[$user->id][$post->id];
    }

    public function loadCurrentUserPostPermissionCollection(Post $post)
    {        
        return $this->loadUserPostPermissionCollection($this->repository->getCurrentUser(), $post);
    }
}