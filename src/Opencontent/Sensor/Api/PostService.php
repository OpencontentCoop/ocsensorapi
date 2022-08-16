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
     * @param $postUuid
     *
     * @return Post
     * @throw \Exception
     */
    public function loadPostByUuid($postUuid);

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
    public function refreshPost(Post $post, $modifyTimestamp = true);

    /**
     * @param Post $post
     * @return void
     */
    public function doRefreshPost(Post $post); //@todo

    /**
     * @param Post $post
     * @param mixed $status
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

    /**
     * @param Post $post
     * @param array $files
     * @throw \Exception
     */
    public function addImage(Post $post, $files);

    /**
     * @param Post $post
     * @param array $files
     * @throw \Exception
     */
    public function removeImage(Post $post, $files);

    /**
     * @param Post $post
     * @param array $files
     * @throw \Exception
     */
    public function addFile(Post $post, $files);

    /**
     * @param Post $post
     * @param array $files
     * @throw \Exception
     */
    public function removeFile(Post $post, $files);

    public function setUserPostAware(Post $post);

    public function setCommentsIsOpen(Post $post);

    /**
     * @param Post $post
     * @param Post\Type $type
     * @throw \Exception
     */
    public function setPostType(Post $post, Post\Type $type);

    /**
     * @param Post $post
     * @param array $tags
     * @return mixed
     */
    public function setPostTags(Post $post, array $tags);

    /**
     * @param Post $post
     * @param array $protocols
     * @throw \Exception
     */
    public function setPostProtocols(Post $post, $protocols);
}
