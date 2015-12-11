<?php

namespace OpenContent\Sensor\Api\Values;

use OpenContent\Sensor\Api\Exportable;

class Post extends Exportable
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $internalId;

    /**
     * @var \DateTime
     */
    public $published;

    /**
     * @var \DateTime
     */
    public $modified;

    /**
     * @var Post\ExpirationInfo
     */
    public $expirationInfo;

    /**
     * @var Post\ResolutionInfo
     */
    public $resolutionInfo;

    /**
     * @var string
     */
    public $subject;

    /**
     * @var string
     */
    public $description;

    /**
     * @var Post\Type
     */
    public $type;

    /**
     * @var Post\Status\Privacy
     */
    public $privacy;

    /**
     * @var Post\Status\Moderation
     */
    public $moderation;

    /**
     * @var Post\Status
     */
    public $status;

    /**
     * @var Post\WorkflowStatus
     */
    public $workflowStatus;

    /**
     * @var ParticipantCollection
     */
    public $participants;

    /**
     * @var Participant
     */
    public $author;

    /**
     * @var Participant
     */
    public $reporter;

    /**
     * @var Participant\ApproverCollection
     */
    public $approvers;

    /**
     * @var Participant\OwnerCollection
     */
    public $owners;

    /**
     * @var Participant\ObserverCollection
     */
    public $observers;

    /**
     * @var Message\TimelineItemCollection
     */
    public $timelineItems;

    /**
     * @var Message\PrivateMessageCollection
     */
    public $privateMessages;

    /**
     * @var Message\CommentCollection
     */
    public $comments;

    /**
     * @var Message\ResponseCollection
     */
    public $responses;

    /**
     * @var bool
     */
    public $commentsIsOpen;

    /**
     * @var Post\Field\Image[]
     */
    public $images;

    /**
     * @var Post\Field\Attachment[]
     */
    public $attachments;

    /**
     * @var Post\Field\Category[]
     */
    public $categories;

    /**
     * @var Post\Field\GeoLocation
     */
    public $geoLocation;

    /**
     * @var Post\Field\Area[]
     */
    public $areas;

    /**
     * @var mixed
     */
    public $internalStatus;

    public static function __set_state( $array )
    {
        /** @var Post $object */
        $object = parent::__set_state( $array );
        $object->internalStatus = 'hit';
        return $object;
    }

}
