<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

/**
 * Class File
 * @package Opencontent\Sensor\Api\Values\Post\Field
 */
class File extends Field
{
    /**
     * @var string
     */
    public $fileName;

    /**
     * @var
     */
    public $downloadUrl;

    /**
     * @var string
     */
    public $mimeType;

    /**
     * @var string
     */
    public $size;

    /**
     * @var string
     */
    public $apiUrl;

    /**
     * @var string
     */
    public $icon;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['fileName']);
        $objectVars['downloadUrl'] = (isset($objectVars['downloadUrl'])) ? '_site_url_/' . $objectVars['downloadUrl'] : '';
        $objectVars['apiUrl'] = '_site_url_/' . $objectVars['apiUrl'];

        return self::toJson($objectVars);
    }
}
