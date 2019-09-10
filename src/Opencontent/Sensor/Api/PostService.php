<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;


interface PostService
{
    /**
     * @param $postId
     *
     * @return Post
     * @throw \Exception
     */
    public function loadPost($postId);

    /**
     * @param $query
     * @param $limit
     * @param $offset
     * @return mixed
     */
    public function loadPosts($query, $limit, $offset);

    /**
     * @param $postInternalId
     *
     * @return Post
     * @throw \Exception
     */
    public function loadPostByInternalId($postInternalId);

    /**
     * @param PostCreateStruct $post
     *
     * @return Post
     * @throw \Exception
     */
    public function createPost(PostCreateStruct $post);

    /**
     * @param PostUpdateStruct $post
     *
     * @return Post
     * @throw \Exception
     */
    public function updatePost(PostUpdateStruct $post);

    /**
     * @param Post $post
     *
     * @return boolean
     * @throw \Exception
     */
    public function deletePost(Post $post);

    /**
     * @param Post $post
     *
     * @return true
     * @throw \Exception
     */
    public function trashPost(Post $post);

    /**
     * @param Post $post
     *
     * @return true
     * @throw \Exception
     */
    public function restorePost(Post $post);

    /**
     * @param Post $post
     * @return Post
     */
    public function refreshPost(Post $post);

    /**
     * @param Post $post
     * @param string $status
     * @throw \Exception
     */
    public function setPostStatus(Post $post, $status);

    /**
     * @param Post $post
     * @param string $status
     * @throw \Exception
     */
    public function setPostWorkflowStatus(Post $post, $status);

    /**
     * @param Post $post
     * @param int $expiryDays
     * @throw \Exception
     */
    public function setPostExpirationInfo(Post $post, $expiryDays);

    /**
     * @param Post $post
     * @param string $category
     * @throw \Exception
     */
    public function setPostCategory(Post $post, $category);

    /**
     * @param Post $post
     * @param string $area
     * @throw \Exception
     */
    public function setPostArea(Post $post, $area);

    /**
     * @param Post $post
     * @param array $files
     * @throw \Exception
     */
    public function addAttachment(Post $post, $files);

    /**
     * @param Post $post
     * @param array $files
     * @throw \Exception
     */
    public function removeAttachment(Post $post, $files);

    public function setUserPostAware(Post $post);

    public function setCommentsIsOpen(Post $post);

}