<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

/**
 * Class Image
 * @package Opencontent\Sensor\Api\Values\Post\Field
 */
class Image extends Field
{
    /**
     * @var string
     */
    public $fileName;

    /**
     * @var array
     */
    public $original;

    /**
     * @var array
     */
    public $thumbnail;

    /**
     * @var string
     */
    public $apiUrl;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['fileName']);
        $objectVars['original'] = (isset($objectVars['original']['url'])) ? '_site_url_/' . $objectVars['original']['url'] : '';
        $objectVars['thumbnail'] = (isset($objectVars['thumbnail']['url'])) ? '_site_url_/' . $objectVars['thumbnail']['url'] : '';
        $objectVars['apiUrl'] = '_site_url_/' . $objectVars['apiUrl'];

        return self::toJson($objectVars);
    }
}