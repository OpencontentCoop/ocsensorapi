<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

class Subscription extends Exportable
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var DateTime
     */
    public $createdAt;

    /**
     * @var integer
     */
    public $userId;

    /**
     * @var integer
     */
    public $postId;

    public function __toString()
    {
        return get_called_class() . '#' . $this->id;
    }
}