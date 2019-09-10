<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

class Attachment extends Field
{
    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $downloadUrl;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);
        $objectVars['downloadUrl'] = '_site_url_/' . $objectVars['downloadUrl'];

        return self::toJson($objectVars);
    }
}