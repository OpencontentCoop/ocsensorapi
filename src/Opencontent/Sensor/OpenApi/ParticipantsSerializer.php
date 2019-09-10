<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Values\ParticipantCollection;

class ParticipantsSerializer extends AbstractSerializer
{
    /**
     * @param ParticipantCollection $item
     * @param array $parameters
     * @return array
     */
    public function serialize($item, array $parameters = [])
    {
        $items = [];
        $data = $item->jsonSerialize();
        foreach ($data as $item) {
            $items[] = $this->serializeParticipant($item);
        }

        return $items;
    }

    private function serializeParticipant(array $item)
    {
        $item['role_id'] = $item['roleIdentifier'];
        unset($item['roleIdentifier']);

        $item['role_name'] = $item['roleName'];
        unset($item['roleName']);

        $item['last_access_at'] = $item['lastAccessDateTime'];
        unset($item['lastAccessDateTime']);

        return $item;
    }

}