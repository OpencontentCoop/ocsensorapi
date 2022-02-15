<?php

namespace Opencontent\Sensor\Legacy\PermissionDefinitions;

class CanAddImage extends \Opencontent\Sensor\Core\PermissionDefinitions\CanAddImage
{
    protected function getMaxNumberOfImages()
    {
        return \OpenPaSensorRepository::instance()->getSensorSettings()->get('UploadMaxNumberOfImages');
    }
}
