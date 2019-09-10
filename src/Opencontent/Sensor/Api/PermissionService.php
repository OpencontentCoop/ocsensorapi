<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Permission\PermissionDefinition;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Values\PermissionCollection;

interface PermissionService
{
    /**
     * @param $identifier
     *
     * @return PermissionDefinition
     */
    public function loadPermissionDefinitionByIdentifier($identifier);

    /**
     * @param Post $post
     *
     * @return PermissionCollection
     */
    public function loadCurrentUserPostPermissionCollection(Post $post);

    /**
     * @param User $user
     * @param Post $post
     *
     * @return PermissionCollection
     */
    public function loadUserPostPermissionCollection(User $user, Post $post);
}