<?php

namespace OpenContent\Sensor\Utils;

use OpenContent\Sensor\Api\Values\ParticipantCollection;
use ezpI18n;
use OpenContent\Sensor\Api\Values\Post\WorkflowStatus;

class TimelineTools
{
    public static function setText( $status, $name )
    {
        if ( is_array( $name ) )
        {
            $name = implode( '::', $name );
        }
        $result = '';
        if ( $status == WorkflowStatus::FIXED )
        {
            if ( $name )
                $result = '_fixed by ' .  $name;
            else
                $result = '_fixed';

        }
        elseif( $status == WorkflowStatus::READ )
        {
            if ( $name )
                $result = '_read by ' .  $name;
            else
                $result = '_read';
        }
        elseif( $status == WorkflowStatus::CLOSED )
        {
            if ( $name )
                $result = '_closed by ' .  $name;
            else
                $result = '_closed';
        }
        elseif( $status == WorkflowStatus::ASSIGNED )
        {
            if ( $name )
                $result = '_assigned to ' .  $name;
            else
                $result = '_assigned';
        }
        elseif( $status == WorkflowStatus::REOPENED )
        {
            if ( $name )
                $result = '_reopened by ' .  $name;
            else
                $result = '_reopened';
        }
        return $result;
    }

    public static function getText( $text, ParticipantCollection $participants )
    {
        $result = '';
        $parts = explode( ' by ', $text );
        if ( !isset( $parts[1] ) )
        {
            $parts = explode( ' to ', $text );
        }
        if ( isset( $parts[1] ) )
        {
            $nameParts = explode( '::', $parts[1] );
            $nameString = array();
            foreach ( $nameParts as $namePart )
            {
                if ( is_numeric( $namePart ) )
                {
                    $participant = $participants->getUserById( intval( $namePart ) );
                    $nameString[] = $participant->name;
                }
                else
                {
                    $nameString[] = $namePart;
                }
            }
            $name = implode( ', ', $nameString );

            switch ( $parts[0] )
            {
                case '_fixed':
                    $result = ezpI18n::tr(
                        'sensor/robot message',
                        'Completata da %name',
                        false,
                        array( '%name' => $name )
                    );
                    break;

                case '_read':
                    $result = ezpI18n::tr(
                        'sensor/robot message',
                        'Letta da %name',
                        false,
                        array( '%name' => $name )
                    );
                    break;

                case '_closed':
                    $result = ezpI18n::tr(
                        'sensor/robot message',
                        'Chiusa da %name',
                        false,
                        array( '%name' => $name )
                    );
                    break;

                case '_assigned':
                    $result = ezpI18n::tr(
                        'sensor/robot message',
                        'Assegnata a %name',
                        false,
                        array( '%name' => $name )
                    );
                    break;

                case '_reopened':
                    $result = ezpI18n::tr(
                        'sensor/robot message',
                        'Riaperta da %name',
                        false,
                        array( '%name' => $name )
                    );
                    break;
            }
        }
        else
        {
            switch ( $parts[0] )
            {
                case '_fixed':
                    $result = ezpI18n::tr( 'sensor/robot message', 'Completata' );
                    break;

                case '_read':
                    $result = ezpI18n::tr( 'sensor/robot message', 'Letta' );
                    break;

                case '_closed':
                    $result = ezpI18n::tr( 'sensor/robot message', 'Chiusa' );
                    break;

                case '_assigned':
                    $result = ezpI18n::tr( 'sensor/robot message', 'Assegnata' );
                    break;

                case '_reopened':
                    $result = ezpI18n::tr( 'sensor/robot message', 'Riaperta' );
                    break;
            }
        }

        return $result;
    }
}