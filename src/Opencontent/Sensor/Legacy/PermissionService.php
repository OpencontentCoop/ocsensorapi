<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Values\Permission;
use Opencontent\Sensor\Api\Values\PermissionCollection;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Core\PermissionDefinitions\SettingPermissionInterface;
use Opencontent\Sensor\Core\PermissionService as BasePermissionService;
use OpenPaSensorRepository;
use Opencontent\Sensor\Core\PermissionDefinitions\ReadOnlyAllowedInterface;

class PermissionService extends BasePermissionService
{
    private static $superUsers = [];

    public function loadUserPostPermissionCollection(User $user, Post $post)
    {
        $permissionCollection = new PermissionCollection();
        foreach ($this->permissionDefinitions as $permissionDefinition) {
            $permission = new Permission();
            $permission->identifier = $permissionDefinition->identifier;

            if (self::isSuperAdmin($user) && !$permissionDefinition instanceof SettingPermissionInterface) {
                $permission->grant = true;
            }elseif (OpenPaSensorRepository::isReadOnlyModeEnabled() && !$permissionDefinition instanceof ReadOnlyAllowedInterface){
                $permission->grant = false;
            }else{
                $permission->grant = (bool)$permissionDefinition->userHasPermission($user, $post);
            }
            $permissionCollection->addPermission($permission);
        }
        return $permissionCollection;
    }

    //@todo move to user property
    public static function isSuperAdmin(User $user)
    {
        if (!isset(self::$superUsers[$user->id])) {
            self::$superUsers[$user->id] = false;
            $ezUser = \eZUser::fetch($user->id);
            if ($ezUser instanceof \eZUser) {
                $accessArray = $ezUser->accessArray();
                self::$superUsers[$user->id] = isset($accessArray['*']['*']);
            }
        }

        return self::$superUsers[$user->id];
    }
}
