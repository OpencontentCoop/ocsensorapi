<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post\Type;

interface PostTypeService
{
    /**
     * @return Type[]
     */
    public function loadPostTypes();

    /**
     * @param $identifier
     * @return Type
     */
    public function loadPostType($identifier);
}