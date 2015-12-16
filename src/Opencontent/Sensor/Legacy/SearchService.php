<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Core\SearchService as BaseSearchService;
use OpenContent\Sensor\Api\Values\Post;
use OpenContent\Sensor\Api\SearchQuery;

class SearchService extends BaseSearchService
{
    /**
     * @var Repository
     */
    protected $repository;

    protected $fieldsMapper = array (
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
        'observer_id_list' => 'sensor_observer_id_list_lk',
        'observer_name_list' => 'sensor_observer_name_list_lk',
        'history_owner_name' => 'sensor_history_owner_name_lk',
        'history_owner_id' => 'sensor_history_owner_id_lk',

        'area_name_list' => 'sensor_area_name_list_lk',
        'area_id_list' => 'sensor_area_id_list_lk',
        'category_name_list' => 'sensor_category_name_list_lk',
        'category_id_list' => 'sensor_category_id_list_lk',

        'user_*_has_read' => 'sensor_user_*_has_read_b',
        'user_*_unread_timelines' => 'sensor_user_*_unread_timelines_i',
        'user_*_timelines' => 'sensor_user_*_timelines_i',
        'user_*_unread_comments' => 'sensor_user_*_unread_comments_i',
        'user_*_comments' => 'sensor_user_*_comments_i',
        'user_*_unread_private_messages' => 'sensor_user_*_unread_private_messages_i',
        'user_*_private_messages' => 'sensor_user_*_private_messages_i',
        'user_*_unread_responses' => 'sensor_user_*_unread_responses_i',
        'user_*_responses' => 'sensor_user_*_responses_i'
    );

    public function getSolrFields( Post $post )
    {
        $data = array();

        $data['sensor_internalid_si'] = $post->internalId;
        $data['sensor_subject_t'] = $post->subject;
        $data['sensor_type_s'] = $post->type->identifier;
        $data['sensor_status_lk'] = $post->status->identifier;
        $data['sensor_workflow_status_lk'] = $post->workflowStatus->identifier;
        $data['sensor_privacy_lk'] = $post->privacy->identifier;
        $data['sensor_moderation_lk'] = $post->moderation->identifier;

        if ( $post->geoLocation->latitude )
        {
            $data['sensor_coordinates_gpt'] = $post->geoLocation->latitude . ',' . $post->geoLocation->longitude;
            $data['sensor_address_t'] = $post->geoLocation->address;
        }

        $data['sensor_expiration_dt'] = strftime( '%Y-%m-%dT%H:%M:%SZ', $post->expirationInfo->expirationDateTime->format( 'U' ) );
        $data['sensor_expiration_days_i'] = $post->expirationInfo->days;

        if ( $post->author )
        {
            $data['sensor_author_id_i'] = $post->author->id;
            $data['sensor_author_name_t'] = $post->author->name;
        }

        if ( $post->reporter )
        {
            $data['sensor_reporter_id_i'] = $post->reporter->id;
            $data['sensor_reporter_name_t'] = $post->reporter->name;
        }

        if ( $post->author && $post->reporter )
            $data['sensor_behalf_b'] = ( $post->author->name != $post->reporter->name ) ? 'true' : 'false';

        $data['sensor_open_dt'] = strftime( '%Y-%m-%dT%H:%M:%SZ', $post->published->format( 'U' ) );
        $data['sensor_open_timestamp_i'] = $post->published->format( 'U' );
        $data['sensor_open_weekday_si'] = $post->published->format( 'w' );

        $read = $post->timelineItems->getByType( 'read' )->first();
        if ( $read )
        {
            $data['sensor_read_timestamp_i'] = $read->published->format( 'U' );
            $data['sensor_read_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $read->published->format( 'U' )
            );
            $interval = $post->published->diff( $read->published );
            $data['sensor_reading_time_i'] = Utils::getDateIntervalSeconds( $interval );
        }

        $assignedList = $post->timelineItems->getByType( 'assigned' );
        $assigned = $assignedList->first();
        if ( $assigned )
        {
            $data['sensor_assigned_timestamp_i'] = $assigned->published->format( 'U' );
            $data['sensor_assigned_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $assigned->published->format( 'U' )
            );
            $interval = $post->published->diff( $assigned->published );
            $data['sensor_assigning_time_i'] = Utils::getDateIntervalSeconds( $interval );
        }

        $fix = $post->timelineItems->getByType( 'fixed' )->last();
        if ( $fix )
        {
            $data['sensor_fix_timestamp_i'] = $fix->published->format( 'U' );
            $data['sensor_fix_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $fix->published->format( 'U' )
            );

            $interval = $post->published->diff( $fix->published );
            $data['sensor_fixing_time_i'] = Utils::getDateIntervalSeconds( $interval );
        }

        $close = $post->timelineItems->getByType( 'closed' )->last();
        if ( $close )
        {
            $data['sensor_close_timestamp_i'] = $close->published->format( 'U' );
            $data['sensor_close_dt'] = strftime(
                '%Y-%m-%dT%H:%M:%SZ',
                $close->published->format( 'U' )
            );

            $interval = $post->published->diff( $close->published );
            $data['sensor_closing_time_i'] = Utils::getDateIntervalSeconds( $interval );
        }

        if ( isset( $data['sensor_reading_time_i'] ) )
            $data['sensor_open_read_time_i'] = $data['sensor_reading_time_i'];

        if ( isset( $data['sensor_reading_time_i'] ) && isset( $data['sensor_assigning_time_i'] ) )
            $data['sensor_read_assign_time_i'] = $data['sensor_assigning_time_i'] - $data['sensor_reading_time_i'];

        if ( isset( $data['sensor_assigning_time_i'] ) && isset( $data['sensor_fixing_time_i'] ) )
            $data['sensor_assign_fix_time_i'] = $data['sensor_fixing_time_i'] - $data['sensor_assigning_time_i'];

        if ( isset( $data['sensor_fixing_time_i'] ) && isset( $data['sensor_closing_time_i'] ) )
            $data['sensor_fix_close_time_i'] = $data['sensor_closing_time_i'] - $data['sensor_fixing_time_i'];;

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

        $currentUser = $this->repository->getCurrentUser();
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
        $this->repository->setCurrentUser( $currentUser );
        return $data;
    }

    public function instanceNewSearchQuery()
    {
        return new SearchQuery();
    }

    public function mappedField( $solrField )
    {
        $values = array_flip( $this->fieldsMapper );
        return $values[$solrField];
    }

    public function field( $field )
    {
        return $this->fieldsMapper[$field];
    }

    public function query( SearchQuery $query )
    {
        $solr = new \OCSolr();

        $facet = array();
        foreach( $query->facets as $facetString )
        {
            $facet[] = array( 'field' => $this->fieldsMapper[$facetString], 'limit' => $query->facetLimit );
        }

        $sort = array();
        foreach( $query->sortArray as $sortArray )
        {
            foreach( $sortArray as $field => $value )
                $sort[$this->fieldsMapper[$field]] = $value;
        }

        $filter = array();
        foreach( $query->filters as $filterName => $filterValue )
        {
            if ( is_array( $filterValue ) )
            {
                $filterValueArray = count( $filterValue ) > 1 ? array( 'or' ) : array();
                foreach( $filterValue as $value )
                {
                    $filterValueArray[] = $this->fieldsMapper[$filterName] . ':' . $value;
                }
                $filter[] = $filterValueArray;
            }
            else
            {
                $filter[] = $this->fieldsMapper[$filterName] . ':' . $filterValue;
            }
        }

        $fields = array();
        foreach( $query->fields as $field )
        {
            $fields[] = $this->fieldsMapper[$field];
        }
        
        $filter[] = \eZSolr::getMetaFieldName( 'installation_id' ) . ':' . \eZSolr::installationID();

        $params = array(
            'SearchOffset' => $query->limits[1],
            'SearchLimit' => $query->limits[0],
            'Facet' => $facet,
            'SortBy' => $sort,
            'Filter' => $filter,
            'SearchContentClassID' => array( $this->repository->getPostContentClass()->attribute( 'id' ) ),
            'SearchSectionID' => null,
            'SearchSubTreeArray' => array( $this->repository->getPostRootNode()->attribute( 'node_id' ) ),
            'AsObjects' => false,
            'SpellCheck' => null,
            'IgnoreVisibility' => null,
            'Limitation' => array(),
            'BoostFunctions' => null,
            'QueryHandler' => 'ezpublish',
            'EnableElevation' => true,
            'ForceElevation' => true,
            'SearchDate' => null,
            'DistributedSearch' => false,
            'FieldsToReturn' => $fields,
            'SearchResultClustering' => null,
            'ExtendedAttributeFilter' => array()
        );

        return $solr->search( $query->query, $params );
    }
}
