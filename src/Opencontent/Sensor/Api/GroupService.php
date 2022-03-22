<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Group;

interface GroupService
{
    public function loadGroup($groupId, $limitations = null);

    public function loadGroups($query, $limit, $cursor, $limitations = null);

    public function createGroup(array $payload);

    public function updateGroup(Group $group, array $payload);
}
