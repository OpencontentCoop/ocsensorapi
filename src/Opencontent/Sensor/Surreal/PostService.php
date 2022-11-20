<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\PostService as CorePostService;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\PostCreateStruct;
use Opencontent\Sensor\Api\Values\PostUpdateStruct;

class PostService extends CorePostService
{

    /**
     * @inheritDoc
     */
    public function loadPost($postId)
    {
        // TODO: Implement loadPost() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostByUuid($postUuid)
    {
        // TODO: Implement loadPostByUuid() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPosts($query, $limit, $offset)
    {
        // TODO: Implement loadPosts() method.
    }

    /**
     * @inheritDoc
     */
    public function createPost(PostCreateStruct $post)
    {
        // TODO: Implement createPost() method.
    }

    /**
     * @inheritDoc
     */
    public function updatePost(PostUpdateStruct $post)
    {
        // TODO: Implement updatePost() method.
    }

    /**
     * @inheritDoc
     */
    public function deletePost(Post $post)
    {
        // TODO: Implement deletePost() method.
    }

    /**
     * @inheritDoc
     */
    public function trashPost(Post $post)
    {
        // TODO: Implement trashPost() method.
    }

    /**
     * @inheritDoc
     */
    public function restorePost(Post $post)
    {
        // TODO: Implement restorePost() method.
    }

    /**
     * @inheritDoc
     */
    public function refreshPost(Post $post, $modifyTimestamp = true)
    {
        // TODO: Implement refreshPost() method.
    }

    /**
     * @inheritDoc
     */
    public function doRefreshPost(Post $post)
    {
        // TODO: Implement doRefreshPost() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostStatus(Post $post, $status)
    {
        // TODO: Implement setPostStatus() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostWorkflowStatus(Post $post, $status)
    {
        // TODO: Implement setPostWorkflowStatus() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostExpirationInfo(Post $post, $expiryDays)
    {
        // TODO: Implement setPostExpirationInfo() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostCategory(Post $post, $category)
    {
        // TODO: Implement setPostCategory() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostArea(Post $post, $area)
    {
        // TODO: Implement setPostArea() method.
    }

    /**
     * @inheritDoc
     */
    public function addAttachment(Post $post, $files)
    {
        // TODO: Implement addAttachment() method.
    }

    /**
     * @inheritDoc
     */
    public function removeAttachment(Post $post, $files)
    {
        // TODO: Implement removeAttachment() method.
    }

    /**
     * @inheritDoc
     */
    public function addImage(Post $post, $files)
    {
        // TODO: Implement addImage() method.
    }

    /**
     * @inheritDoc
     */
    public function removeImage(Post $post, $files)
    {
        // TODO: Implement removeImage() method.
    }

    /**
     * @inheritDoc
     */
    public function addFile(Post $post, $files)
    {
        // TODO: Implement addFile() method.
    }

    /**
     * @inheritDoc
     */
    public function removeFile(Post $post, $files)
    {
        // TODO: Implement removeFile() method.
    }

    public function setUserPostAware(Post $post)
    {
        // TODO: Implement setUserPostAware() method.
    }

    public function setCommentsIsOpen(Post $post)
    {
        // TODO: Implement setCommentsIsOpen() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostType(Post $post, Post\Type $type)
    {
        // TODO: Implement setPostType() method.
    }

    /**
     * @inheritDoc
     */
    public function setPostTags(Post $post, array $tags)
    {
        // TODO: Implement setPostTags() method.
    }
}