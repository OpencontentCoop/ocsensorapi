<?php

namespace Opencontent\Sensor\Api;


use Opencontent\Sensor\Api\Values\Post;

interface SearchService
{
    const MAX_LIMIT = 100;

    const DEFAULT_LIMIT = 10;

    /**
     * @param $postId
     * @param array $parameters
     * @return Post
     */
    public function searchPost($postId, $parameters = array());

    /**
     * @param $query
     * @param array $parameters
     * @return mixed
     */
    public function searchPosts($query, $parameters = array(), $policies = null);

}