<?php

namespace OpenContent\Sensor\Api;

use OpenContent\Sensor\Api\Permission\PermissionDefinition;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Api\Values\PermissionCollection;

interface PermissionService
{
    /**
     * @param $identifier
     *
     * @return PermissionDefinition
     */
    public function loadPermissionDefinitionByIdentifier( $identifier );

    /**
     * @param User $user
     * @param Post $post
     *
     * @return PermissionCollection
     */
    public function loadUserPostPermissionCollection( User $user, Post $post );
}