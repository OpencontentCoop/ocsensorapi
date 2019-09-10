<?php

namespace Opencontent\Sensor\Legacy\PostService\Scenarios;

use Opencontent\Sensor\Legacy\PostService\ScenarioInterface;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;

class FallbackScenario implements ScenarioInterface
{
    public function match(Post $post, User $user)
    {
        return true;
    }

    public function getApprovers()
    {
        $admin = \eZUser::fetchByName( 'admin' );
        if ($admin instanceof \eZUser){
            return [$admin->id()];
        }

        return [];
    }

    public function getOwners()
    {
        return [];
    }

    public function getObservers()
    {
        return [];
    }

}