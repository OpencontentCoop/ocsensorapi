<?php

namespace Opencontent\Sensor\Api\Values\Post;

use \DateTime;
use Opencontent\Sensor\Api\Exportable;

/**
 * Class ExpirationInfo
 * @package Opencontent\Sensor\Api\Values\Post
 */
class ExpirationInfo extends Exportable
{
    /**
     * @var DateTime
     */
    public $expirationDateTime;

    /**
     * @var DateTime
     */
    public $creationDateTime;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $text;

    /**
     * @var int
     */
    public $days;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['creationDateTime']);

        return self::toJson($objectVars);
    }
}