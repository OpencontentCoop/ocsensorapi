<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\Values\User;

class UserSerializer extends AbstractSerializer
{
    /**
     * @param User $item
     * @param array $parameters
     *
     * @return array
     */
    public function serialize($item, array $parameters = [])
    {
        $user = [
            'id' => (int)$item->id,
            'name' => trim($item->name),
            'description' => trim($item->description),
            'email' => $item->email,
            'last_access_at' => $this->formatDate($item->lastAccessDateTime),
            'is_moderated' => (boolean)$item->moderationMode,
            'can_comment' => (boolean)$item->commentMode,
            'can_post_on_behalf_of' => (boolean)$item->behalfOfMode,
            'is_enabled' => (boolean)$item->isEnabled,
            'type' => $item->type,
            'groups' => $item->groups,
        ];

        return $user;
    }

}