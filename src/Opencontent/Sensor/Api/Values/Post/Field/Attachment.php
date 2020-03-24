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

    /**
     * @var string
     */
    public $apiUrl;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);
        $objectVars['downloadUrl'] = '_site_url_/' . $objectVars['downloadUrl'];
        $objectVars['apiUrl'] = '_site_url_/' . $objectVars['apiUrl'];

        return self::toJson($objectVars);
    }
}