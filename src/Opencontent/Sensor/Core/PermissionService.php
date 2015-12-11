<?php

namespace OpenContent\Sensor\Core;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\PermissionService as PermissionServiceInterface;
use OpenContent\Sensor\Api\Values\Permission;
use OpenContent\Sensor\Api\Values\PermissionCollection;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Exception\BaseException;

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

    /**
     * @param Repository $repository
     * @param PermissionDefinition[] $permissionDefinitions
     */
    public function __construct( Repository $repository, $permissionDefinitions )
    {
        $this->repository = $repository;
        $this->permissionDefinitions = $permissionDefinitions;
    }

    public function loadPermissionDefinitionByIdentifier( $identifier )
    {
        foreach( $this->permissionDefinitions as $permissionDefinition )
        {
            if ( $permissionDefinition->identifier == $identifier )
                return $permissionDefinition;
        }
        throw new BaseException( "Permission $identifier not found" );
    }

    public function loadUserPostPermissionCollection( User $user, Post $post )
    {
        $permissionCollection = new PermissionCollection();
        foreach( $this->permissionDefinitions as $permissionDefinition )
        {
            $permission = new Permission();
            $permission->identifier = $permissionDefinition->identifier;
            $permission->grant = (bool) $permissionDefinition->userHasPermission( $user, $post );
            $permissionCollection->addPermission( $permission );
        }
        return $permissionCollection;
    }
}