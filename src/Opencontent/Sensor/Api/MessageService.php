<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Post;

interface MessageService
{

    /**
     * @param Post $post
     * @return void
     */
    public function loadPostComments(Post $post);

    /**
     * @param Post $post
     * @return void
     */
    public function loadPostPrivateMessages(Post $post);

    /**
     * @param Post $post
     * @return void
     */
    public function loadPostTimelineItems(Post $post);

    /**
     * @param Post $post
     * @return void
     */
    public function loadPostResponses(Post $post);

    /**
     * @param Post $post
     * @return void
     */
    public function loadPostAudits(Post $post);

    /**
     * @param Post $post
     *
     * @return Message/CommentCollection
     */
    public function loadCommentCollectionByPost(Post $post);


    /**
     * @param Post $post
     *
     * @return Message/PrivateMessageCollection
     */
    public function loadPrivateMessageCollectionByPost(Post $post);

    /**
     * @param Post $post
     *
     * @return Message/TimelineItemCollection
     */
    public function loadTimelineItemCollectionByPost(Post $post);

    /**
     * @param Post $post
     *
     * @return Message/ResponseCollection
     */
    public function loadResponseCollectionByPost(Post $post);

    public function addTimelineItemByWorkflowStatus(Post $post, $status, $parameters = null);

    public function createTimelineItem(Message\TimelineItemStruct $struct);

    public function createPrivateMessage(Message\PrivateMessageStruct $struct);

    /**
     * @param Message\PrivateMessageStruct $struct
     *
     * @return Message\PrivateMessage
     */
    public function updatePrivateMessage(Message\PrivateMessageStruct $struct);

    public function createComment(Message\CommentStruct $struct);

    /**
     * @param Message\CommentStruct $struct
     *
     * @return Message\Comment
     */
    public function updateComment(Message\CommentStruct $struct);

    public function createResponse(Message\ResponseStruct $struct);

    /**
     * @param Message\ResponseStruct $struct
     *
     * @return Message\Response
     */
    public function updateResponse(Message\ResponseStruct $struct);

    /**
     * @param Post $post
     *
     * @return Message\AuditCollection
     */
    public function loadAuditCollectionByPost(Post $post);

    public function createAudit(Message\AuditStruct $struct);

    public function loadMessageFromExternalId($externalId);
}
