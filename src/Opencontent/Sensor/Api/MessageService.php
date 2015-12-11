<?php

namespace OpenContent\Sensor\Api;

use OpenContent\Sensor\Api\Values\Message;
use OpenContent\Sensor\Api\Values\Post;

interface MessageService
{

    /**
     * @param Post $post
     *
     * @return Message/CommentCollection
     */
    public function loadCommentCollectionByPost( Post $post );


    /**
     * @param Post $post
     *
     * @return Message/PrivateMessageCollection
     */
    public function loadPrivateMessageCollectionByPost( Post $post );

    /**
     * @param Post $post
     *
     * @return Message/TimelineItemCollection
     */
    public function loadTimelineItemCollectionByPost( Post $post );

    /**
     * @param Post $post
     *
     * @return Message/ResponseCollection
     */
    public function loadResponseCollectionByPost( Post $post );

    public function addTimelineItemByWorkflowStatus( Post $post, $status, $parameters = null );

    public function createTimelineItem( Message\TimelineItemStruct $struct );

    public function createPrivateMessage( Message\PrivateMessageStruct $struct );

    /**
     * @param Message\PrivateMessageStruct $struct
     *
     * @return Message\PrivateMessage
     */
    public function updatePrivateMessage( Message\PrivateMessageStruct $struct );

    public function createComment( Message\CommentStruct $struct );

    /**
     * @param Message\CommentStruct $struct
     *
     * @return Message\Comment
     */
    public function updateComment( Message\CommentStruct $struct );

    public function createResponse( Message\ResponseStruct $struct );

    /**
     * @param Message\ResponseStruct $struct
     *
     * @return Message\Response
     */
    public function updateResponse( Message\ResponseStruct $struct );

}