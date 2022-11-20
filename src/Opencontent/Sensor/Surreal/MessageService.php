<?php

namespace Opencontent\Sensor\Surreal;

use Opencontent\Sensor\Core\MessageService as CoreMessageService;
use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\Post;

class MessageService extends CoreMessageService
{

    /**
     * @inheritDoc
     */
    public function loadPostComments(Post $post)
    {
        // TODO: Implement loadPostComments() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostPrivateMessages(Post $post)
    {
        // TODO: Implement loadPostPrivateMessages() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostTimelineItems(Post $post)
    {
        // TODO: Implement loadPostTimelineItems() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostResponses(Post $post)
    {
        // TODO: Implement loadPostResponses() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPostAudits(Post $post)
    {
        // TODO: Implement loadPostAudits() method.
    }

    /**
     * @inheritDoc
     */
    public function loadCommentCollectionByPost(Post $post)
    {
        // TODO: Implement loadCommentCollectionByPost() method.
    }

    /**
     * @inheritDoc
     */
    public function loadPrivateMessageCollectionByPost(Post $post)
    {
        // TODO: Implement loadPrivateMessageCollectionByPost() method.
    }

    /**
     * @inheritDoc
     */
    public function loadTimelineItemCollectionByPost(Post $post)
    {
        // TODO: Implement loadTimelineItemCollectionByPost() method.
    }

    /**
     * @inheritDoc
     */
    public function loadResponseCollectionByPost(Post $post)
    {
        // TODO: Implement loadResponseCollectionByPost() method.
    }

    public function addTimelineItemByWorkflowStatus(Post $post, $status, $parameters = null)
    {
        // TODO: Implement addTimelineItemByWorkflowStatus() method.
    }

    public function createTimelineItem(Message\TimelineItemStruct $struct)
    {
        // TODO: Implement createTimelineItem() method.
    }

    public function createPrivateMessage(Message\PrivateMessageStruct $struct)
    {
        // TODO: Implement createPrivateMessage() method.
    }

    /**
     * @inheritDoc
     */
    public function updatePrivateMessage(Message\PrivateMessageStruct $struct)
    {
        // TODO: Implement updatePrivateMessage() method.
    }

    public function createComment(Message\CommentStruct $struct)
    {
        // TODO: Implement createComment() method.
    }

    /**
     * @inheritDoc
     */
    public function updateComment(Message\CommentStruct $struct)
    {
        // TODO: Implement updateComment() method.
    }

    public function createResponse(Message\ResponseStruct $struct)
    {
        // TODO: Implement createResponse() method.
    }

    /**
     * @inheritDoc
     */
    public function updateResponse(Message\ResponseStruct $struct)
    {
        // TODO: Implement updateResponse() method.
    }

    /**
     * @inheritDoc
     */
    public function loadAuditCollectionByPost(Post $post)
    {
        // TODO: Implement loadAuditCollectionByPost() method.
    }

    public function createAudit(Message\AuditStruct $struct)
    {
        // TODO: Implement createAudit() method.
    }
}