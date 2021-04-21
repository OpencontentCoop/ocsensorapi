<?php

namespace Opencontent\Sensor\Legacy\SearchService;

use Opencontent\Sensor\Api\Repository;
use Opencontent\Sensor\Api\Values\Participant;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Legacy\Utils;

class SolrMapper
{
    const SOLR_STORAGE_POST = 'sensorpost';
    const SOLR_STORAGE_EXECUTION_TIMES = 'sensorpostexec';
    const SOLR_STORAGE_READ_STATUSES = 'sensorpostread';

    private $post;

    private $repository;

    public function __construct(Repository $repository, Post $post)
    {
        $this->repository = $repository;
        $this->post = $post;
    }

    public static function getMap()
    {
        return array(

            'post_id' => 'sensor_post_id_si',
            'internalId' => 'sensor_internalid_si',
            'subject' => 'sensor_subject_t',

            'type' => 'sensor_type_s',
            'status' => 'sensor_status_lk',
            'workflow_status' => 'sensor_workflow_status_lk',
            'privacy' => 'sensor_privacy_lk',
            'moderation' => 'sensor_moderation_lk',

            'coordinates' => 'sensor_coordinates_gpt',
            'address' => 'sensor_address_t',

            'expiration' => 'sensor_expiration_dt',
            'expiration_days' => 'sensor_expiration_days_i',

            'open' => 'sensor_open_dt',
            'open_timestamp' => 'sensor_open_timestamp_i',
            'open_weekday' => 'sensor_open_weekday_si',
            'read_timestamp' => 'sensor_read_timestamp_i',
            'read' => 'sensor_read_dt',
            'reading_time' => 'sensor_reading_time_i',
            'assigned_timestamp' => 'sensor_assigned_timestamp_i',
            'assigned' => 'sensor_assigned_dt',
            'assigning_time' => 'sensor_assigning_time_i',
            'fix_timestamp' => 'sensor_fix_timestamp_i',
            'fix' => 'sensor_fix_dt',
            'fixing_time' => 'sensor_fixing_time_i',
            'close_timestamp' => 'sensor_close_timestamp_i',
            'close' => 'sensor_close_dt',
            'closing_time' => 'sensor_closing_time_i',

            'open_read_time' => 'sensor_open_read_time_i',
            'read_assign_time' => 'sensor_read_assign_time_i',
            'assign_fix_time' => 'sensor_assign_fix_time_i',
            'fix_close_time' => 'sensor_fix_close_time_i',

            'author_id' => 'sensor_author_id_i',
            'author_name' => 'sensor_author_name_t',
            'reporter_id' => 'sensor_reporter_id_i',
            'reporter_name' => 'sensor_reporter_name_t',
            'behalf' => 'sensor_behalf_b',
            'participant_id_list' => 'sensor_participant_id_list_lk',
            'participant_name_list' => 'sensor_participant_name_list_lk',
            'approver_id_list' => 'sensor_approver_id_list_lk',
            'approver_name_list' => 'sensor_approver_name_list_lk',
            'owner_id_list' => 'sensor_owner_id_list_lk',
            'owner_name_list' => 'sensor_owner_name_list_lk',
            'owner_user_id_list' => 'sensor_owner_user_id_list_lk',
            'owner_group_id_list' => 'sensor_owner_group_id_list_lk',
            'observer_id_list' => 'sensor_observer_id_list_lk',
            'observer_name_list' => 'sensor_observer_name_list_lk',
            'history_owner_name' => 'sensor_history_owner_name_lk',
            'history_owner_id' => 'sensor_history_owner_id_lk',
            'last_owner_user_id' => 'sensor_last_owner_user_id_i',
            'last_owner_group_id' => 'sensor_last_owner_group_id_i',

            'area_name_list' => 'sensor_area_name_list_lk',
            'area_id_list' => 'sensor_area_id_list_lk',
            'category_name_list' => 'sensor_category_name_list_lk',
            'category_id_list' => 'sensor_category_id_list_lk',

            'unmoderated_comments' =>'sensor_unmoderated_comments_comments_i',

            'user_*_has_read' => 'sensor_user_*_has_read_b',
            'user_*_last_access_timestamp' => 'sensor_user_*_last_access_timestamp_i',
            'user_*_unread_timelines' => 'sensor_user_*_unread_timelines_i',
            'user_*_timelines' => 'sensor_user_*_timelines_i',
            'user_*_unread_comments' => 'sensor_user_*_unread_comments_i',
            'user_*_comments' => 'sensor_user_*_comments_i',
            'user_*_unread_private_messages' => 'sensor_user_*_unread_private_messages_i',
            'user_*_unread_private_messages_as_receiver' => 'sensor_user_*_unread_private_messages_as_receiver_i',
            'user_*_private_messages' => 'sensor_user_*_private_messages_i',
            'user_*_unread_responses' => 'sensor_user_*_unread_responses_i',
            'user_*_responses' => 'sensor_user_*_responses_i',

            'day' => 'sensor_day_i',
            'month' => 'sensor_week_i',
            'month' => 'sensor_month_i',
            'quarter' => 'sensor_quarter_i',
            'semester' => 'sensor_semester_i',
            'year' => 'sensor_year_i',

            'first_assignment_day' => 'sensor_first_assignment_day_i',
            'first_assignment_month' => 'sensor_first_assignment_week_i',
            'first_assignment_month' => 'sensor_first_assignment_month_i',
            'first_assignment_quarter' => 'sensor_first_assignment_quarter_i',
            'first_assignment_semester' => 'sensor_first_assignment_semester_i',
            'first_assignment_year' => 'sensor_first_assignment_year_i',

            'closing_day' => 'sensor_closing_day_i',
            'closing_month' => 'sensor_closing_week_i',
            'closing_month' => 'sensor_closing_month_i',
            'closing_quarter' => 'sensor_closing_quarter_i',
            'closing_semester' => 'sensor_closing_semester_i',
            'closing_year' => 'sensor_closing_year_i',

            'related_id_list' => 'sensor_related_id_list_lk',

            //'published' => 'published',
            //'modified' => 'modified',
        );
    }

    public function mapToIndex()
    {
        $data = array();

        $data['sensor_post_id_si'] = $this->post->id;
        $data['sensor_internalid_si'] = $this->post->internalId;
        $data['sensor_subject_t'] = $this->post->subject;
        $data['sensor_type_s'] = $this->post->type->identifier;
        $data['sensor_status_lk'] = $this->post->status->identifier;
        $data['sensor_workflow_status_lk'] = $this->post->workflowStatus->identifier;
        $data['sensor_privacy_lk'] = $this->post->privacy->identifier;
        $data['sensor_moderation_lk'] = $this->post->moderation->identifier;

        if ($this->post->geoLocation->latitude) {
            $data['sensor_coordinates_gpt'] = $this->post->geoLocation->latitude . ',' . $this->post->geoLocation->longitude;
            $data['sensor_address_t'] = $this->post->geoLocation->address;
        }

        if ($this->post->expirationInfo->expirationDateTime instanceof \DateTime) {
            $data['sensor_expiration_dt'] = strftime('%Y-%m-%dT%H:%M:%SZ', $this->post->expirationInfo->expirationDateTime->format('U'));
            $data['sensor_expiration_days_i'] = $this->post->expirationInfo->days;
        }

        if ($this->post->author) {
            $data['sensor_author_id_i'] = $this->post->author->id;
            $data['sensor_author_name_t'] = $this->post->author->name;
        }

        if ($this->post->reporter) {
            $data['sensor_reporter_id_i'] = $this->post->reporter->id;
            $data['sensor_reporter_name_t'] = $this->post->reporter->name;
        }

        if ($this->post->author && $this->post->reporter)
            $data['sensor_behalf_b'] = ($this->post->author->id != $this->post->reporter->id) ? 'true' : 'false';

        if ($this->post->published instanceof \DateTime) {
            $data['sensor_open_dt'] = strftime('%Y-%m-%dT%H:%M:%SZ', $this->post->published->format('U'));
            $data['sensor_open_timestamp_i'] = (int)$this->post->published->format('U');
            $data['sensor_open_weekday_si'] = $this->post->published->format('w');
        }

        $assignedList = false;
        $firstAssignmentDate = false;
        $closingDate = false;
        if ($this->post->timelineItems instanceof \Opencontent\Sensor\Api\Values\Message\TimelineItemCollection) {
            $read = $this->post->timelineItems->getByType('read')->first();
            if ($read && $read->published instanceof \DateTime) {
                $data['sensor_read_timestamp_i'] = (int)$read->published->format('U');
                $data['sensor_read_dt'] = strftime(
                    '%Y-%m-%dT%H:%M:%SZ',
                    $read->published->format('U')
                );
                $interval = $this->post->published->diff($read->published);
                $data['sensor_reading_time_i'] = Utils::getDateIntervalSeconds($interval);
            }

            $assignedList = $this->post->timelineItems->getByType('assigned');
            $assigned = $assignedList->first();
            if ($assigned && $assigned->published instanceof \DateTime) {
                $data['sensor_assigned_timestamp_i'] = (int)$assigned->published->format('U');
                $data['sensor_assigned_dt'] = strftime(
                    '%Y-%m-%dT%H:%M:%SZ',
                    $assigned->published->format('U')
                );
                $interval = $this->post->published->diff($assigned->published);
                $data['sensor_assigning_time_i'] = Utils::getDateIntervalSeconds($interval);
                $firstAssignmentDate = $assigned->published;
            }

            $fix = $this->post->timelineItems->getByType('fixed')->last();
            if ($fix && $fix->published instanceof \DateTime) {
                $data['sensor_fix_timestamp_i'] = (int)$fix->published->format('U');
                $data['sensor_fix_dt'] = strftime(
                    '%Y-%m-%dT%H:%M:%SZ',
                    $fix->published->format('U')
                );

                $interval = $this->post->published->diff($fix->published);
                $data['sensor_fixing_time_i'] = Utils::getDateIntervalSeconds($interval);
            }

            $close = $this->post->timelineItems->getByType('closed')->last();
            if ($close && $close->published instanceof \DateTime) {
                $data['sensor_close_timestamp_i'] = (int)$close->published->format('U');
                $data['sensor_close_dt'] = strftime(
                    '%Y-%m-%dT%H:%M:%SZ',
                    $close->published->format('U')
                );

                $interval = $this->post->published->diff($close->published);
                $data['sensor_closing_time_i'] = Utils::getDateIntervalSeconds($interval);
                $closingDate = $close->published;
            }
        }

        if (isset($data['sensor_reading_time_i']))
            $data['sensor_open_read_time_i'] = $data['sensor_reading_time_i'];

        if (isset($data['sensor_reading_time_i']) && isset($data['sensor_assigning_time_i']))
            $data['sensor_read_assign_time_i'] = $data['sensor_assigning_time_i'] - $data['sensor_reading_time_i'];

        if (isset($data['sensor_assigning_time_i']) && isset($data['sensor_fixing_time_i']))
            $data['sensor_assign_fix_time_i'] = $data['sensor_fixing_time_i'] - $data['sensor_assigning_time_i'];

        if (isset($data['sensor_fixing_time_i']) && isset($data['sensor_closing_time_i']))
            $data['sensor_fix_close_time_i'] = $data['sensor_closing_time_i'] - $data['sensor_fixing_time_i'];;

        if ($this->post->participants instanceof \Opencontent\Sensor\Api\Values\ParticipantCollection) {
            $data['sensor_participant_id_list_lk'] = implode(',', $this->post->participants->getUserIdList());
            $participantNameList = array();
            foreach ($this->post->participants->participants as $participant)
                $participantNameList[] = $participant->name;
            $data['sensor_participant_name_list_lk'] = implode(',', $participantNameList);
        }

        if ($this->post->approvers instanceof \Opencontent\Sensor\Api\Values\Participant\ApproverCollection) {
            $data['sensor_approver_id_list_lk'] = implode(',', $this->post->approvers->getUserIdList());
            $participantNameList = array();
            foreach ($this->post->approvers->participants as $participant)
                $participantNameList[] = $participant->name;
            $data['sensor_approver_name_list_lk'] = implode(',', $participantNameList);
        }

        if ($this->post->owners instanceof \Opencontent\Sensor\Api\Values\Participant\OwnerCollection) {
            $data['sensor_owner_id_list_lk'] = implode(',', $this->post->owners->getUserIdList());
            $data['sensor_owner_name_list_lk'] = [];
            $participantNameList = array();
            $participantIdList = [];
            $participantGroupIdList = [];
            foreach ($this->post->owners->participants as $participant) {
                $participantNameList[] = $participant->name;
                if ($participant->type == Participant::TYPE_USER) {
                    $participantIdList[] = $participant->id;
                }elseif($participant->type == Participant::TYPE_GROUP){
                    $participantGroupIdList[] = $participant->id;
                }
            }
            $data['sensor_owner_user_id_list_lk'] = implode(',', $participantIdList);
            $data['sensor_owner_name_list_lk'] = implode(',', $participantNameList);
            $data['sensor_owner_group_id_list_lk'] = implode(',', $participantGroupIdList);
        }

        if ($this->post->observers instanceof \Opencontent\Sensor\Api\Values\Participant\ObserverCollection) {
            $data['sensor_observer_id_list_lk'] = implode(',', $this->post->observers->getUserIdList());
            $participantNameList = array();
            foreach ($this->post->observers->participants as $participant)
                $participantNameList[] = $participant->name;
            $data['sensor_observer_name_list_lk'] = implode(',', $participantNameList);
        }

        $ownerHistory = array();
        $lastOwnerUserId = 0;
        $lastOwnerGroupId = 0;
//        $lastAssignmentDate = false;
        if ($assignedList) {
            foreach ($assignedList->messages as $message) {
                foreach ($message->extra as $id) {
                    $participant = $this->post->participants->getParticipantById($id);
                    if ($participant) {
                        $ownerHistory[$participant->id] = $participant->name;
                        if ($participant->type == Participant::TYPE_GROUP){
                            $lastOwnerGroupId = $participant->id;
                        }elseif ($participant->type == Participant::TYPE_USER){
                            $lastOwnerUserId = $participant->id;
                        }
                    }
                }
//                $lastAssignmentDate = $message->published;
            }
        }
        $data['sensor_history_owner_name_lk'] = implode(',', $ownerHistory);
        $data['sensor_history_owner_id_lk'] = implode(',', array_keys($ownerHistory));
        if ($lastOwnerUserId > 0){
            $data['sensor_last_owner_user_id_i'] = $lastOwnerUserId;
        }
        if ($lastOwnerGroupId > 0){
            $data['sensor_last_owner_group_id_i'] = $lastOwnerGroupId;
        }

        $areaList = array();
        foreach ($this->post->areas as $area)
            $areaList[$area->id] = $area->name;
        $data['sensor_area_name_list_lk'] = implode(',', array_values($areaList));
        $data['sensor_area_id_list_lk'] = implode(',', array_keys($areaList));

        $categoryList = array();
        foreach ($this->post->categories as $category)
            $categoryList[$category->id] = $category->name;
        $data['sensor_category_name_list_lk'] = implode(',', array_values($categoryList));
        $data['sensor_category_id_list_lk'] = implode(',', array_keys($categoryList));

        foreach ($this->generateDateTimeIndexes($this->post->published) as $key => $value){
            $data['sensor_' .$key . '_i'] = $value;
        }

        if ($firstAssignmentDate instanceof \DateTimeInterface){
            foreach ($this->generateDateTimeIndexes($firstAssignmentDate) as $key => $value){
                $data['sensor_first_assignment_' .$key . '_i'] = $value;
            }
        }
        if ($closingDate instanceof \DateTimeInterface){
            foreach ($this->generateDateTimeIndexes($closingDate) as $key => $value){
                $data['sensor_closing_' .$key . '_i'] = $value;
            }
        }

        $data['sensor_related_id_list_lk'] = implode(',', $this->post->relatedItems);

        $solrStorage = new \ezfSolrStorage();
        $value = $solrStorage->serializeData(serialize($this->post));
        $identifier = $solrStorage->getSolrStorageFieldName(self::SOLR_STORAGE_POST);
        $data[$identifier] = $value;

        if ($this->post->participants instanceof \Opencontent\Sensor\Api\Values\ParticipantCollection) {
            $currentUser = $this->repository->getCurrentUser();
            foreach ($this->post->participants->participants as $participant) {
                foreach ($participant->users as $user) {

                    $this->repository->setCurrentUser($user);
                    $post = clone $this->post;
                    $this->repository->getPostService()->setUserPostAware($post);

                    $data['sensor_user_' . $user->id . '_has_read_b'] = $user->hasRead ? 'true' : 'false';
                    if ($user->lastAccessDateTime instanceof \DateTime ) {
                        $data['sensor_user_' . $user->id . '_last_access_timestamp_i'] = (int)$user->lastAccessDateTime->format('U');
                    }else{
                        $data['sensor_user_' . $user->id . '_last_access_timestamp_i'] = 0;
                    }

                    $unreadTimeLines = 0;
                    foreach ($post->timelineItems->messages as $message) {
                        if ($message->creator->id != $user->id && $message->modified > $user->lastAccessDateTime) {
                            $unreadTimeLines++;
                        }
                    }
                    $data['sensor_user_' . $user->id . '_unread_timelines_i'] = $unreadTimeLines;
                    $data['sensor_user_' . $user->id . '_timelines_i'] = $post->timelineItems->count();

                    $unreadComments = 0;
                    $unmoderatedComments = 0;
                    foreach ($post->comments->messages as $message) {
                        if ($message->creator->id != $user->id && $message->modified > $user->lastAccessDateTime) {
                            $unreadComments++;
                        }
                        if ($message->needModeration){
                            $unmoderatedComments++;
                        }
                    }
                    $data['sensor_user_' . $user->id . '_unread_comments_i'] = $unreadComments;
                    $data['sensor_unmoderated_comments_comments_i'] = $unmoderatedComments;
                    $data['sensor_user_' . $user->id . '_comments_i'] = $post->comments->count();

                    $unreadResponses = 0;
                    foreach ($post->responses->messages as $message) {
                        if ($message->creator->id != $user->id && $message->modified > $user->lastAccessDateTime) {
                            $unreadResponses++;
                        }
                    }
                    $data['sensor_user_' . $user->id . '_unread_responses_i'] = $unreadResponses;
                    $data['sensor_user_' . $user->id . '_responses_i'] = $post->responses->count();

                    $unreadPrivates = 0;
                    $unreadPrivatesAsReceiver = 0;
                    foreach ($post->privateMessages->messages as $message) {
                        if ($message->creator->id != $user->id) {
                            if ($message->modified > $user->lastAccessDateTime) {
                                $unreadPrivates++;
                                if ($message->getReceiverById($user->id)){
                                    $unreadPrivatesAsReceiver++;
                                }
                            }
                        }
                    }
                    $data['sensor_user_' . $user->id . '_unread_private_messages_i'] = $unreadPrivates;
                    $data['sensor_user_' . $user->id . '_unread_private_messages_as_receiver_i'] = $unreadPrivatesAsReceiver;
                    $data['sensor_user_' . $user->id . '_private_messages_i'] = $post->privateMessages->count();
                }
            }
            $this->repository->setCurrentUser($currentUser);
        }

        $executionTimesIdentifier = $solrStorage->getSolrStorageFieldName(self::SOLR_STORAGE_EXECUTION_TIMES);
        $executionTimesValue = $solrStorage->serializeData($this->getExecutionTimesData($data));
        $data[$executionTimesIdentifier] = $executionTimesValue;

        $readStatusesIdentifier = $solrStorage->getSolrStorageFieldName(self::SOLR_STORAGE_READ_STATUSES);
        $readStatusesValue = $solrStorage->serializeData($this->getReadStatusesData($data));
        $data[$readStatusesIdentifier] = $readStatusesValue;

        return $data;
    }

    private function generateDateTimeIndexes(\DateTimeInterface $dateTime)
    {
        $month = $dateTime->format('n');
        if ($month >= 10) $quarter = 4;
        elseif ($month >= 7) $quarter = 3;
        elseif ($month >= 4) $quarter = 2;
        else $quarter = 1;

        if ($month >= 6) $semester = 2;
        else $semester = 1;

        $data['day'] = $dateTime->format('Yz');
        $weekNum = $dateTime->format('W');
        if ($weekNum == 53){
            $weekNum = $dateTime->format('m') == '01' ? '01' : '52';
        }
        $data['week'] = $dateTime->format('Y') . $weekNum;
        $data['month'] = $dateTime->format('Ym');
        $data['quarter'] = $dateTime->format('Y') . $quarter;
        $data['semester'] = $dateTime->format('Y') . $semester;
        $data['year'] = $dateTime->format('Y');

        return $data;
    }

    public static function getExecutionTimesMap()
    {
        return array(
            'expiration' => 'sensor_expiration_dt',
            'expiration_days' => 'sensor_expiration_days_i',
            'open' => 'sensor_open_dt',
            'open_timestamp' => 'sensor_open_timestamp_i',
            'open_weekday' => 'sensor_open_weekday_si',
            'read_timestamp' => 'sensor_read_timestamp_i',
            'read' => 'sensor_read_dt',
            'reading_time' => 'sensor_reading_time_i',
            'assigned_timestamp' => 'sensor_assigned_timestamp_i',
            'assigned' => 'sensor_assigned_dt',
            'assigning_time' => 'sensor_assigning_time_i',
            'fix_timestamp' => 'sensor_fix_timestamp_i',
            'fix' => 'sensor_fix_dt',
            'fixing_time' => 'sensor_fixing_time_i',
            'close_timestamp' => 'sensor_close_timestamp_i',
            'close' => 'sensor_close_dt',
            'closing_time' => 'sensor_closing_time_i',
            'open_read_time' => 'sensor_open_read_time_i',
            'read_assign_time' => 'sensor_read_assign_time_i',
            'assign_fix_time' => 'sensor_assign_fix_time_i',
            'fix_close_time' => 'sensor_fix_close_time_i',
        );
    }

    private function getExecutionTimesData($data)
    {
        $statData = [];
        foreach (self::getExecutionTimesMap() as $key => $solrKey) {
            if (isset($data[$solrKey])) {
                $statData[$key] = $data[$solrKey];
            }
        }

        return $statData;
    }

    public static function getReadStatusesMap()
    {
        return array(
            'user_*_has_read' => 'sensor_user_*_has_read_b',
            'user_*_last_access_timestamp' => 'sensor_user_*_last_access_timestamp_i',
            'user_*_unread_timelines' => 'sensor_user_*_unread_timelines_i',
            'user_*_timelines' => 'sensor_user_*_timelines_i',
            'user_*_unread_comments' => 'sensor_user_*_unread_comments_i',
            'user_*_comments' => 'sensor_user_*_comments_i',
            'user_*_unread_private_messages' => 'sensor_user_*_unread_private_messages_i',
            'user_*_unread_private_messages_as_receiver' => 'sensor_user_*_unread_private_messages_as_receiver_i',
            'user_*_private_messages' => 'sensor_user_*_private_messages_i',
            'user_*_unread_responses' => 'sensor_user_*_unread_responses_i',
            'user_*_responses' => 'sensor_user_*_responses_i',
        );
    }

    private function getReadStatusesData($data)
    {
        $statData = [];
        foreach ($data as $solrKey => $value) {
            if (strpos($solrKey, 'sensor_user_') !== false) {
                $key = str_replace(['sensor_', '_b', '_i'], '', $solrKey);
                $statData[$key] = strpos($solrKey, '_b') !== false ? (int)($data[$solrKey] === 'true') : (int)$data[$solrKey];
            }
        }

        return $statData;
    }
}