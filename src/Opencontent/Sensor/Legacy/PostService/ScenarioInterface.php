<?php

namespace Opencontent\Sensor\Legacy\PostService;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

interface ScenarioInterface
{
    const HIGH = 100;

    const MEDIUM = 50;

    const LOW = 1;

    public function match(Post $post, User $user);

    public function getApprovers();

    public function getOwners();

    public function getObservers();
}