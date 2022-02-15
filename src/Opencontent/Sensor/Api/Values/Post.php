<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exportable;

/**
 * Class Post
 * @package Opencontent\Sensor\Api\Values
 * @property array $executionTimes
 * @property array $readingStatuses
 * @property array $capabilities
 * @property array $commentsToModerate
 */
class Post extends Exportable
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $uuid;

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
     * @var User
     */
    public $author;

    /**
     * @var User
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
     * @var Message\AuditCollection
     */
    public $audits;

    /**
     * @var bool
     */
    public $commentsIsOpen;

    /**
     * @var Post\Field\Image[]
     */
    public $images = [];

    /**
     * @var Post\Field\File[]
     */
    public $files = [];

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
     * @var array
     */
    public $meta;

    /**
     * @var integer[]
     */
    public $relatedItems = [];

    /**
     * @var Post\Channel
     */
    public $channel;

    /**
     * @var Participant
     */
    public $latestOwner;

    /**
     * @var Participant
     */
    public $latestOwnerGroup;

    public function jsonSerialize()
    {
        $objectVars = get_object_vars($this);

        return self::toJson($objectVars);
    }

}
