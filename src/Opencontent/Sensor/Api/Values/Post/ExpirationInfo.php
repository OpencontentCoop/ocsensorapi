<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 21/11/15
 * Time: 12:55
 */

namespace OpenContent\Sensor\Api\Values\Post;

use \DateTime;
use OpenContent\Sensor\Api\Exportable;

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

}