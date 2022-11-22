<?php

namespace Opencontent\Sensor\Api\Values\Post;

use Opencontent\Sensor\Api\Exportable;

class DeployInfo extends Exportable
{
    /**
     * @var DateTime
     */
    public $validFrom;

    /**
     * @var DateTime
     */
    public $validTo;

    /**
     * @var string
     */
    public $documentNumber;
}