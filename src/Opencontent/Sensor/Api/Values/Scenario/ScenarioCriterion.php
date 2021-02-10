<?php

namespace Opencontent\Sensor\Api\Values\Scenario;

use Opencontent\Sensor\Api\Values\Post;

interface ScenarioCriterion
{
    /**
     * @return string
     */
    public function getIdentifier();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @param Post $post
     * @return bool
     */
    public function match(Post $post);
}