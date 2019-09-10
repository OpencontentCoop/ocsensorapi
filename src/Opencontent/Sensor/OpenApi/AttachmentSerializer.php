<?php

namespace Opencontent\Sensor\OpenApi;


use Opencontent\Sensor\Api\Values\Post\Field\Attachment;

class AttachmentSerializer extends AbstractSerializer
{
    /**
     * @param Attachment $item
     * @param array $parameters
     * @return mixed
     */
    public function serialize($item, array $parameters = [])
    {
        return $this->apiSettings->replacePlaceholders($item->jsonSerialize()['downloadUrl']);
    }

}