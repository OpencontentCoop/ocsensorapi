<?php

namespace Opencontent\Sensor\Legacy\PermissionDefinitions;


class CanAddFile extends \Opencontent\Sensor\Core\PermissionDefinitions\CanAddFile
{
    protected function getMaxNumberOfFiles()
    {
        return \OpenPaSensorRepository::instance()->getSensorSettings()->get('UploadMaxNumberOfFiles');
    }
}
