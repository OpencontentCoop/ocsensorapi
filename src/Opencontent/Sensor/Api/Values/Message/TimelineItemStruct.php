<?php

namespace OpenContent\Sensor\Api\Values\Message;

use OpenContent\Sensor\Api\Values\MessageStruct;
use OpenContent\Sensor\Api\Values\Post\WorkflowStatus;
use OpenContent\Sensor\Api\Values\User;

class TimelineItemStruct extends MessageStruct
{
    /**
     * @var string
     */
    public $status;

    public function createTextMessage( $name = null )
    {
        if ( is_array( $name ) )
        {
            $name = implode( '::', $name );
        }
        $this->text = '';
        if ( $this->status == WorkflowStatus::FIXED )
        {
            if ( $name )
                $this->text = '_fixed by ' .  $name;
            else
                $this->text = '_fixed';

        }
        elseif( $this->status == WorkflowStatus::READ )
        {
            if ( $name )
                $this->text = '_read by ' .  $name;
            else
                $this->text = '_read';
        }
        elseif( $this->status == WorkflowStatus::CLOSED )
        {
            if ( $name )
                $this->text = '_closed by ' .  $name;
            else
                $this->text = '_closed';
        }
        elseif( $this->status == WorkflowStatus::ASSIGNED )
        {
            if ( $name )
                $this->text = '_assigned to ' .  $name;
            else
                $this->text = '_assigned';
        }
        elseif( $this->status == WorkflowStatus::REOPENED )
        {
            if ( $name )
                $this->text = '_reopened by ' .  $name;
            else
                $this->text = '_reopened';
        }
    }
}