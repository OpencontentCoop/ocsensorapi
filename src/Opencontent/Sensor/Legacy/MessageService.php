<?php

namespace Opencontent\Sensor\Legacy;

use eZCollaborationItemMessageLink;
use eZCollaborationSimpleMessage;
use eZPersistentObject;
use Opencontent\Sensor\Api\Values\Message;
use Opencontent\Sensor\Api\Values\MessageStruct;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Core\MessageService as MessageServiceBase;
use Opencontent\Sensor\Legacy\Utils\TimelineTools;

class MessageService extends MessageServiceBase
{

    const TIMELINE_ITEM = 0;

    const COMMENT = 1;

    const RESPONSE = 2;

    const WORKGROUP = 3;

    const AUDIT = 4;

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var int
     */
    protected $countMessagesByPost = array();

    /**
     * @var Message\CommentCollection[]
     */
    protected $commentsByPost = array();

    /**
     * @var Message\PrivateMessageCollection[]
     */
    protected $privateMessagesByPost = array();

    /**
     * @var Message\TimelineItemCollection[]
     */
    protected $timelineItemsByPost = array();

    /**
     * @var Message\ResponseCollection[]
     */
    protected $responsesByPost = array();

    /**
     * @var Message\AuditCollection[]
     */
    protected $auditByPost = array();

    private function clearMemoryCache($post)
    {
        unset($this->countMessagesByPost[$post->internalId]);
        unset($this->commentsByPost[$post->internalId]);
        unset($this->privateMessagesByPost[$post->internalId]);
        unset($this->timelineItemsByPost[$post->internalId]);
        unset($this->responsesByPost[$post->internalId]);
    }

    public function loadCommentCollectionByPost(Post $post)
    {
        $this->internalLoadMessagesByPost($post);
        return $this->commentsByPost[$post->internalId];
    }

    public function loadPrivateMessageCollectionByPost(Post $post)
    {
        $this->internalLoadMessagesByPost($post);
        return $this->privateMessagesByPost[$post->internalId];
    }

    public function loadTimelineItemCollectionByPost(Post $post)
    {
        $this->internalLoadMessagesByPost($post);
        return $this->timelineItemsByPost[$post->internalId];
    }

    public function loadResponseCollectionByPost(Post $post)
    {
        $this->internalLoadMessagesByPost($post);
        return $this->responsesByPost[$post->internalId];
    }

    protected function internalLoadMessagesByPost(Post $post)
    {
        $postInternalId = $post->internalId;
        if (!isset($this->countMessagesByPost[$postInternalId])) {
            $this->countMessagesByPost[$postInternalId] = 0;
            $this->commentsByPost[$postInternalId] = new Message\CommentCollection();
            $this->privateMessagesByPost[$postInternalId] = new Message\PrivateMessageCollection();
            $this->timelineItemsByPost[$postInternalId] = new Message\TimelineItemCollection();
            $this->responsesByPost[$postInternalId] = new Message\ResponseCollection();
            $this->auditByPost[$postInternalId] = new Message\AuditCollection();


            /** @var eZCollaborationItemMessageLink[] $messageLinks */
            $messageLinks = eZPersistentObject::fetchObjectList(
                eZCollaborationItemMessageLink::definition(),
                null,
                array('collaboration_id' => $postInternalId),
                array('created' => 'asc'),
                null,
                true
            );

            $simpleMessageIdList = array();
            foreach ($messageLinks as $messageLink) {
                $simpleMessageIdList[] = $messageLink->attribute('message_id');
            }

            $simpleMessages = array();
            if (!empty($simpleMessageIdList)) {
                /** @var eZCollaborationSimpleMessage[] $simpleMessages */
                $simpleMessages = eZPersistentObject::fetchObjectList(
                    eZCollaborationSimpleMessage::definition(),
                    null,
                    array('id' => array($simpleMessageIdList)),
                    array('created' => 'asc'),
                    null,
                    true
                );
            }

            $messageData = array();
            foreach ($simpleMessages as $simpleMessage) {
                $messageItem = array(
                    'message' => $simpleMessage,
                    'links' => array()
                );
                foreach ($messageLinks as $messageLink) {
                    if ($messageLink->attribute('message_id') == $simpleMessage->attribute('id')) {
                        $messageItem['links'][] = $messageLink;
                    }
                }
                $messageData[] = $messageItem;
            }

            foreach ($messageData as $messageItem) {
                if (count($messageItem['links']) > 0) {
                    /** @var eZCollaborationSimpleMessage $simpleMessage */
                    $simpleMessage = $messageItem['message'];

                    /** @var eZCollaborationItemMessageLink $firstLink */
                    $firstLink = $messageItem['links'][0];

                    if ($firstLink->attribute('message_type') == self::COMMENT) {
                        $message = new Message\Comment();
                        $type = 'comment';
                        $message->text = $simpleMessage->attribute('data_text1');
                        $message->needModeration = $simpleMessage->attribute('data_int1') == 1;
                        $message->isRejected = $simpleMessage->attribute('data_int2') == 1;

                    } elseif ($firstLink->attribute('message_type') == self::RESPONSE) {
                        $message = new Message\Response();
                        $type = 'response';
                        $message->text = $simpleMessage->attribute('data_text1');

                    } elseif ($firstLink->attribute('message_type') == self::TIMELINE_ITEM) {
                        $message = new Message\TimelineItem();
                        $type = 'timeline';
                        $message->type = TimelineTools::getType($simpleMessage->attribute('data_text1'));
                        $message->extra = TimelineTools::getExtra($simpleMessage->attribute('data_text1'));
                        $message->text = TimelineTools::getText(
                            $simpleMessage->attribute('data_text1'),
                            $this->repository->getParticipantService()->loadPostParticipants($post),
                            $simpleMessage->attribute('creator_id')
                        );

                    } elseif ($firstLink->attribute('message_type') == self::AUDIT) {
                        $message = new Message\Audit();
                        $type = 'audit';
                        $message->text = $simpleMessage->attribute('data_text1');

                    } else {
                        $message = new Message\PrivateMessage();
                        $type = 'private';
                        $message->text = $simpleMessage->attribute('data_text1');
                        $message->isResponseProposal = $simpleMessage->attribute('data_int1') == 1;
                        /** @var eZCollaborationItemMessageLink $link */
                        foreach ($messageItem['links'] as $link) {
                            $participant = $this->repository->getParticipantService()
                                ->loadPostParticipants($post)
                                ->getParticipantById($link->attribute('message_type'));
                            if ($participant instanceof Participant) {
                                $message->receivers[] = $participant;
                            }
                        }
                    }
                    $message->id = $simpleMessage->attribute('id');
                    $creator = new User();
                    $creator->id = $simpleMessage->attribute('id');
                    $creator = $this->repository->getParticipantService()
                        ->loadPostParticipants($post)
                        ->getUserById($simpleMessage->attribute('creator_id'));
                    if ($creator instanceof User)
                        $message->creator = $creator;
                    else
                        $message->creator = $this->repository->getUserService()->loadUser(
                            $simpleMessage->attribute('creator_id')
                        );

                    $message->published = Utils::getDateTimeFromTimestamp($simpleMessage->attribute('created'));
                    $message->modified = Utils::getDateTimeFromTimestamp($simpleMessage->attribute('modified'));

                    $message->text = htmlspecialchars($message->text);
                    $message->richText = $this->formatText($message->text);

                    if ($type == 'response')
                        $this->responsesByPost[$postInternalId]->addMessage($message);

                    elseif ($type == 'comment')
                        $this->commentsByPost[$postInternalId]->addMessage($message);

                    elseif ($type == 'timeline')
                        $this->timelineItemsByPost[$postInternalId]->addMessage($message);

                    elseif ($type == 'audit')
                        $this->auditByPost[$postInternalId]->addMessage($message);

                    elseif ($type == 'private')
                        $this->privateMessagesByPost[$postInternalId]->addMessage($message);

                    $this->countMessagesByPost[$postInternalId]++;
                }
            }
        }
    }

    private function formatText($string)
    {
        $ini = \eZINI::instance("template.ini");
        $max = $ini->variable('AutoLinkOperator', 'MaxCharacters');
        $methods = $ini->variable('AutoLinkOperator', 'Methods');
        $methodText = implode('|', $methods);

        // Replace mail
        $string = preg_replace("#(([a-zA-Z0-9_-]+\\.)*[a-zA-Z0-9_-]+@([a-zA-Z0-9_-]+\\.)*[a-zA-Z0-9_-]+)#", "<a href='mailto:\\1'>\\1</a>", $string);
        $autoLinkOperator = new \eZAutoLinkOperator();
        $string = $autoLinkOperator->addURILinks($string, $max, $methodText);

        $string = nl2br($string);

        return $string;
    }

    public function addTimelineItemByWorkflowStatus(Post $post, $status, $parameters = null)
    {
        $struct = new Message\TimelineItemStruct();
        $struct->post = $post;
        $struct->creator = $this->repository->getCurrentUser();
        $struct->status = $status;
        $struct->createdDateTime = new \DateTime();
        if ($parameters === null)
            $parameters = $struct->creator->id;
        $struct->text = TimelineTools::setText($status, $parameters);
        $this->createTimelineItem($struct);
        $this->clearMemoryCache($post);
    }

    public function createTimelineItem(Message\TimelineItemStruct $struct)
    {
        $message = $this->createMessage($struct);
        $this->linkMessage($message, $struct, self::TIMELINE_ITEM);
    }

    public function createPrivateMessage(Message\PrivateMessageStruct $struct)
    {
        $message = $this->createMessage($struct);
        $message->setAttribute('data_text2', implode(',', $struct->receiverIdList));
        $message->store();
        if (empty($struct->receiverIdList)){
            $this->linkMessage($message, $struct, self::WORKGROUP);
        }else {
            foreach ($struct->receiverIdList as $id) {
                $this->linkMessage($message, $struct, $id);
            }
        }
    }

    public function updatePrivateMessage(Message\PrivateMessageStruct $struct)
    {
        return $this->updateMessage($struct);
    }

    public function createComment(Message\CommentStruct $struct)
    {
        $message = $this->createMessage($struct);
        $this->linkMessage($message, $struct, self::COMMENT);
    }

    public function updateComment(Message\CommentStruct $struct)
    {
        return $this->updateMessage($struct);
    }

    public function createResponse(Message\ResponseStruct $struct)
    {
        $message = $this->createMessage($struct);
        $this->linkMessage($message, $struct, self::RESPONSE);
    }

    public function updateResponse(Message\ResponseStruct $struct)
    {
        return $this->updateMessage($struct);
    }

    protected function updateMessage(MessageStruct $struct)
    {
        if ($struct->id !== null) {
            $simpleMessage = eZCollaborationSimpleMessage::fetch($struct->id);

            $needUpdate = $struct->text != '' && $struct->text != $simpleMessage->attribute('data_text1');

            if ($struct instanceof Message\PrivateMessageStruct){
                $needUpdate = $needUpdate || (int)$struct->isResponseProposal != $simpleMessage->attribute('data_int1');
            }

            if ($struct instanceof Message\CommentStruct){
                $needUpdate = $needUpdate || (int)$struct->needModeration != $simpleMessage->attribute('data_int1');
            }

            if ($simpleMessage instanceof eZCollaborationSimpleMessage
                && $simpleMessage->attribute('creator_id') == $struct->creator->id
                && $needUpdate) {
                $simpleMessage->setAttribute('data_text1', $struct->text);

                if ($struct instanceof Message\PrivateMessageStruct){
                    $simpleMessage->setAttribute('data_int1', (int)$struct->isResponseProposal);
                }

                if ($struct instanceof Message\CommentStruct){
                    $simpleMessage->setAttribute('data_int1', (int)$struct->needModeration);
                    $simpleMessage->setAttribute('data_int2', (int)$struct->isRejected);
                }

                $now = time();
                $simpleMessage->setAttribute('modified', $now);
                $simpleMessage->store();
                return $simpleMessage;
            }
        }
        return false;
    }

    protected function createMessage(MessageStruct $struct)
    {
        $time = time();

        $fields = [
            'message_type' => $this->repository->getSensorCollaborationHandlerTypeString() . '_comment',
            'data_text1' => $struct->text,
            'created' => $time,
            'modified' => $time,
            'creator_id' => $struct->creator->id,
        ];
        if ($struct instanceof Message\PrivateMessageStruct){
            $fields['data_int1'] = (int)$struct->isResponseProposal;
        }elseif ($struct instanceof Message\CommentStruct){
            $fields['data_int1'] = (int)$struct->needModeration;
            $fields['data_int2'] = (int)$struct->isRejected;
        }

        // avoid duplicated message in same second
        $simpleMessage = eZCollaborationSimpleMessage::fetchObject(eZCollaborationSimpleMessage::definition(), null, $fields, true);

        if (!$simpleMessage instanceof eZCollaborationSimpleMessage) {
            $simpleMessage = new eZCollaborationSimpleMessage($fields);
            $simpleMessage->store();
        }

        return $simpleMessage;
    }

    protected function linkMessage(eZCollaborationSimpleMessage $message, MessageStruct $struct, $type)
    {
        $time = time();
        $fields = [
            'collaboration_id' => $struct->post->internalId,
            'message_id' => $message->ID,
            'message_type' => $type,
            'participant_id' => $struct->creator->id,
            'created' => $time,
            'modified' => $time
        ];

        // avoid duplicated message in same second
        $messageLink = eZCollaborationItemMessageLink::fetchObject(eZCollaborationItemMessageLink::definition(), null, $fields, true);
        if (!$messageLink instanceof eZCollaborationItemMessageLink) {
            $db = \eZDB::instance();
            $db->begin();
            $messageLink = new eZCollaborationItemMessageLink($fields);
            $messageLink->store();
            $db->commit();
        }

        $this->repository->getUserService()->setLastAccessDateTime($struct->creator, $struct->post);
    }

    /**
     * @param Post $post
     *
     * @return Message\AuditCollection
     */
    public function loadAuditCollectionByPost(Post $post)
    {
        $this->internalLoadMessagesByPost($post);
        return $this->auditByPost[$post->internalId];
    }

    public function createAudit(Message\AuditStruct $struct)
    {
        $message = $this->createMessage($struct);
        $this->linkMessage($message, $struct, self::AUDIT);
    }
}