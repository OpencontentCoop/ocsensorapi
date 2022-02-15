<?php

namespace Opencontent\Sensor\Legacy\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanRemoveFile extends \Opencontent\Sensor\Core\PermissionDefinitions\CanRemoveFile
{
    public function userHasPermission(User $user, Post $post)
    {
        $object = \eZContentObject::fetch($post->id);
        if ($object instanceof \eZContentObject) {
            $dataMap = $object->dataMap();
            if (!isset($dataMap['files']) || $dataMap['files']->attribute('data_type_string') != 'ocmultibinary'){
                return false;
            }
        }

        return parent::userHasPermission($user, $post);
    }
}
