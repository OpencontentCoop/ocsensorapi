<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post\Status;

interface PostStatusService
{
    /**
     * @return Status[]
     */
    public function loadPostStatuses();

    /**
     * @param $identifier
     * @return Status
     */
    public function loadPostStatus($identifier);
}