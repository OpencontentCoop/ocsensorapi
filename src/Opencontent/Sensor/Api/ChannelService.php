<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post\Channel;

interface ChannelService
{
    /**
     * @return Channel[]
     */
    public function loadPostChannels();

    /**
     * @param $name
     * @return Channel
     */
    public function loadPostChannel($name);

    /**
     * @return Channel
     */
    public function loadPostDefaultChannel();
}