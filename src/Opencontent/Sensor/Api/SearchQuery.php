<?php

namespace OpenContent\Sensor\Api;

class SearchQuery
{
    protected $availableFields = array(

        'id',
        'internalId',
        'subject',

        'type',
        'status',
        'workflow_status',
        'privacy',
        'moderation',

        'coordinates',
        'address',

        'expiration',
        'expiration_days',

        'open',
        'open_timestamp',
        'open_weekday',
        'read_timestamp',
        'read',
        'reading_time',
        'assigned_timestamp',
        'assigned',
        'assigning_time',
        'close_timestamp',
        'close',
        'closing_time',

        'open_read_time',
        'read_assign_time',
        'assign_fix_time',
        'fix_close_time',

        'author_id',
        'author_name',
        'reporter_id',
        'reporter_name',
        'behalf',
        'participant_id_list',
        'participant_name_list',
        'approver_id_list',
        'approver_name_list',
        'owner_id_list',
        'owner_name_list',
        'observer_id_list',
        'observer_name_list',
        'history_owner_name',
        'history_owner_id',

        'area_name_list',
        'area_id_list',
        'category_name_list',
        'category_id_list',

        'user_*_has_read',
        'user_*_unread_timelines',
        'user_*_timelines',
        'user_*_unread_comments',
        'user_*_comments',
        'user_*_unread_private_messages',
        'user_*_private_messages',
        'user_*_unread_responses',
        'user_*_responses'
    );

    public $fields = array();

    public $facets = array();

    public $filters = array();

    public $limits = array( 10, 0 );

    public $query;

    public $facetLimit = 1000;

    public $sortArray = array();

    public function __construct()
    {
    }

    public function filter( $field, $value )
    {
        if ( in_array( $field, $this->availableFields ) )
        {
            if ( !isset( $this->filters[$field] ) )
            {
                $this->filters[$field] = $value;
            }
            else
            {
                if ( is_array( $value ) )
                {
                    $this->filters[$field] = array_merge(
                        $this->filters[$field],
                        $value
                    );
                }
                else
                {
                    $this->filters[$field][] = $value;
                }
            }
        }

        return $this;
    }

    public function fields( array $fields )
    {
        foreach ( $fields as $field )
        {
            $this->field( $field );
        }

        return $this;
    }

    public function field( $field )
    {
        if ( in_array( $field, $this->availableFields ) )
        {
            $this->fields[] = $field;
        }

        return $this;
    }


    public function facets( array $facets )
    {
        foreach ( $facets as $facet )
        {
            $this->facet( $facet );
        }

        return $this;
    }

    public function facet( $facet )
    {
        if ( in_array( $facet, $this->availableFields ) )
        {
            $this->facets[] = $facet;
        }

        return $this;
    }

    public function limits( $limit, $offset = 0 )
    {
        $this->limits = array( $limit, $offset );

        return $this;
    }

    public function sort( $sortArray )
    {
        $this->sortArray[] = $sortArray;

        return $this;
    }
}
