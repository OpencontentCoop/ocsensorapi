<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Core\SearchService as BaseSearchService;
use OpenContent\Sensor\Api\Values\Post;

class SearchService extends BaseSearchService
{
    /**
     * @var Repository
     */
    protected $repository;

    public function getSolrFields( Post $post )
    {
        $data = array();

        $data['sensor_internalid_si'] = $post->internalId;
        $data['sensor_status_lk'] = $post->status->identifier;
        $data['sensor_workflow_status_lk'] = $post->workflowStatus->identifier;
        $data['sensor_privacy_lk'] = $post->privacy->identifier;
        $data['sensor_moderation_lk'] = $post->moderation->identifier;

        $data['sensor_behalf_b'] = ( $post->author->name != $post->reporter->name ) ? 'true' : 'false';

        $data['sensor_coordinates_gpt'] = $post->geoLocation->latitude . ',' . $post->geoLocation->longitude;
        $data['sensor_address_t'] = $post->geoLocation->address;

        $data['sensor_expiration_dt'] = strftime( '%Y-%m-%dT%H:%M:%SZ', $post->expirationInfo->expirationDateTime->format( 'U' ) );
        $data['sensor_expiration_days_i'] = $post->expirationInfo->days;

        $data['sensor_participant_id_list_lk'] = implode( ',', $post->participants->getParticipantIdList() );
        $participantNameList = array();
        foreach( $post->participants->participants as $participant )
            $participantNameList[] = $participant->name;
        $data['sensor_participant_name_list_lk'] = implode( ',', $participantNameList );
        $data['sensor_approver_id_list_lk'] = implode( ',', $post->approvers->getParticipantIdList() );
        $participantNameList = array();
        foreach( $post->approvers->participants as $participant )
            $participantNameList[] = $participant->name;
        $data['sensor_approver_name_list_lk'] = implode( ',', $participantNameList );
        $data['sensor_owner_id_list_lk'] = implode( ',', $post->owners->getParticipantIdList() );
        $participantNameList = array();
        foreach( $post->owners->participants as $participant )
            $participantNameList[] = $participant->name;
        $data['sensor_owner_name_list_lk'] = implode( ',', $participantNameList );
        $data['sensor_observer_id_list_lk'] = implode( ',', $post->observers->getParticipantIdList() );
        $participantNameList = array();
        foreach( $post->observers->participants as $participant )
            $participantNameList[] = $participant->name;
        $data['sensor_observer_name_list_lk'] = implode( ',', $participantNameList );

        $data['sensor_open_dt'] = strftime( '%Y-%m-%dT%H:%M:%SZ', $post->published->format( 'U' ) );
        $data['sensor_open_weekday_si'] = $post->published->format( 'w' );

        $read = $post->timelineItems->getByType( 'read' )->first();
        if ( $read )
        {
            $data['sensor_read_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $read->published->format( 'U' )
            );
            $interval = $post->published->diff( $read->published );
            $data['sensor_reading_time_i'] = \OpenContent\Sensor\Legacy\Utils::getDateIntervalSeconds( $interval );
        }
        $assignedList = $post->timelineItems->getByType( 'assigned' );
        $assigned = $assignedList->first();
        if ( $assigned )
        {
            $data['sensor_assigned_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $assigned->published->format( 'U' )
            );
            $interval = $post->published->diff( $assigned->published );
            $data['sensor_assignign_time_i'] = \OpenContent\Sensor\Legacy\Utils::getDateIntervalSeconds( $interval );
        }
        $ownerHistory = array();
        foreach( $assignedList->messages as $message )
        {
            foreach( $message->extra as $id )
            {
                $participant = $post->participants->getParticipantById( $id );
                if ( $participant )
                    $ownerHistory[$participant->id] = $participant->name;
            }
        }
        $data['sensor_history_owner_name_lk'] = implode( ',', $ownerHistory );
        $data['sensor_history_owner_id_lk'] = implode( ',', array_keys( $ownerHistory ) );

        $close = $post->timelineItems->getByType( 'closed' )->last();
        if ( $close )
        {
            $data['sensor_close_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $close->published->format( 'U' )
            );

            $interval = $post->published->diff( $close->published );
            $data['sensor_closing_time_i'] = \OpenContent\Sensor\Legacy\Utils::getDateIntervalSeconds( $interval );
        }

        $areaList = array();
        foreach( $post->areas as $area )
            $areaList[$area->id] = $area->name;
        $data['sensor_area_name_list_lk'] = implode( ',', array_values( $areaList ) );
        $data['sensor_area_id_list_lk'] = implode( ',', array_keys( $areaList ) );

        $categoryList = array();
        foreach( $post->categories as $category )
            $categoryList[$category->id] = $category->name;
        $data['sensor_category_name_list_lk'] = implode( ',', array_values( $categoryList ) );
        $data['sensor_category_id_list_lk'] = implode( ',', array_keys( $categoryList ) );

        foreach( $post->participants->participants as $participant )
        {
            foreach ( $participant->users as $user )
            {
                $data['sensor_user_' . $user->id . '_has_read_b'] = $user->hasRead ? 'true' : 'false';

                $this->repository->setCurrentUser( $user );
                $this->repository->getPostService()->setUserPostAware( $post );

                $unreadTimeLines = 0;
                foreach( $post->timelineItems->messages as $message )
                    if ( $message->modified > $user->lastAccessDateTime )
                        $unreadTimeLines++;

                $unreadComments = 0;
                foreach( $post->comments->messages as $message )
                    if ( $message->modified > $user->lastAccessDateTime )
                        $unreadComments++;

                $unreadPrivates = 0;
                foreach( $post->privateMessages->messages as $message )
                    if ( $message->modified > $user->lastAccessDateTime )
                        $unreadPrivates++;

                $unreadResponses = 0;
                foreach( $post->responses->messages as $message )
                    if ( $message->modified > $user->lastAccessDateTime )
                        $unreadResponses++;

                $data['sensor_user_' . $user->id . '_unread_timelines_i'] = $unreadTimeLines;
                $data['sensor_user_' . $user->id . '_timelines_i'] = $post->timelineItems->count();
                $data['sensor_user_' . $user->id . '_unread_comments_i'] = $unreadComments;
                $data['sensor_user_' . $user->id . '_comments_i'] = $post->comments->count();
                $data['sensor_user_' . $user->id . '_unread_private_messages_i'] = $unreadPrivates;
                $data['sensor_user_' . $user->id . '_private_messages_i'] = $post->privateMessages->count();
                $data['sensor_user_' . $user->id . '_unread_responses_i'] = $unreadResponses;
                $data['sensor_user_' . $user->id . '_responses_i'] = $post->responses->count();
            }
        }
        return $data;
    }
}