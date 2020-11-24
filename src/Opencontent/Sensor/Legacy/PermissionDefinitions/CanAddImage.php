<?php

namespace Opencontent\Sensor\Legacy\PermissionDefinitions;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class CanAddImage extends \Opencontent\Sensor\Core\PermissionDefinitions\CanAddImage
{
    /**
     * @var int
     */
    private $maxNumberOfFile;

    public function userHasPermission(User $user, Post $post)
    {
        $object = \eZContentObject::fetch($post->id);
        if ($object instanceof \eZContentObject) {
            $dataMap = $object->dataMap();
            if (!isset($dataMap['images']) || $dataMap['images']->attribute('data_type_string') != 'ocmultibinary'){
                return false;
            }else{
                $this->maxNumberOfFile = (int)$dataMap['images']->contentClassAttribute()->attribute(\OCMultiBinaryType::MAX_NUMBER_OF_FILES_FIELD);
            }
        }

        return parent::userHasPermission($user, $post);
    }

    protected function getImageLimitCount()
    {
        return $this->maxNumberOfFile > 0 ? $this->maxNumberOfFile : parent::getImageLimitCount();
    }

}