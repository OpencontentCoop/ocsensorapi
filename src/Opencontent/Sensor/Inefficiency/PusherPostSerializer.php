<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Stanzadelcittadino\Client\Request\Struct\User;

class PusherPostSerializer extends PostSerializer
{
    public function __construct($severityMap = [])
    {
        parent::__construct($severityMap);
    }

    public function serialize(
        Post $post,
        User $userStruct,
        string $userUuid = null,
        array $images = [],
        array $files = [],
        string $serviceId = "inefficiencies"
    ): array {
        $data = parent::serialize(
            $post,
            $userStruct,
            $userUuid,
            $images,
            $files,
            $serviceId
        );
        $data['status'] = 2000;

        return $data;
    }

}