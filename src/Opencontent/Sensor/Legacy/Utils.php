<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Api\Exception\BaseException;
use DateTime;
use DateInterval;
use ezpI18n;
use OpenContent\Sensor\Api\Values\User;
use OpenContent\Sensor\Utils\DateDiffResult;
use eZUser;

class Utils
{
    /**
     * @param int $timestamp
     * @param int $intervalDays
     *
     * @return int $timestamp
     * @throws BaseException
     */
    public static function addDaysToTimeStamp( $timestamp, $intervalDays )
    {
        $expiringDate = new DateTime();
        $expiringDate->setTimestamp( $timestamp );

        $expiringIntervalString = 'P' . intval( $intervalDays ) . 'D';
        $expiringInterval = new DateInterval( $expiringIntervalString );
        if ( !$expiringInterval instanceof DateInterval )
        {
            throw new BaseException( "Invalid interval {$expiringIntervalString}" );
        }
        $expiringDate->add( $expiringInterval );
        return $expiringDate->format( 'U' );
    }

    public static function getDateTimeFromTimestamp( $timestamp )
    {
        $dateTime = new DateTime();
        $dateTime->setTimestamp( $timestamp );
        return $dateTime;
    }

    public static function getDateDiff( $start, $end = null )
    {
        if ( !$start instanceof DateTime )
        {
            $start = new DateTime( $start );
        }

        if ( $end === null )
        {
            $end = new DateTime();
        }

        if ( !$end instanceof DateTime )
        {
            $end = new DateTime( $start );
        }

        $interval = $end->diff( $start );
        $translate = function ( $nb, $str )
        {
            $string = $nb > 1 ? $str . 's' : $str;
            switch ( $string )
            {
                case 'year';
                    $string = ezpI18n::tr( 'sensor/expiring', 'anno' );
                    break;
                case 'years';
                    $string = ezpI18n::tr( 'sensor/expiring', 'anni' );
                    break;
                case 'month';
                    $string = ezpI18n::tr( 'sensor/expiring', 'mese' );
                    break;
                case 'months';
                    $string = ezpI18n::tr( 'sensor/expiring', 'mesi' );
                    break;
                case 'day';
                    $string = ezpI18n::tr( 'sensor/expiring', 'giorno' );
                    break;
                case 'days';
                    $string = ezpI18n::tr( 'sensor/expiring', 'giorni' );
                    break;
                case 'hour';
                    $string = ezpI18n::tr( 'sensor/expiring', 'ora' );
                    break;
                case 'hours';
                    $string = ezpI18n::tr( 'sensor/expiring', 'ore' );
                    break;
                case 'minute';
                    $string = ezpI18n::tr( 'sensor/expiring', 'minuto' );
                    break;
                case 'minutes';
                    $string = ezpI18n::tr( 'sensor/expiring', 'minuti' );
                    break;
                case 'second';
                    $string = ezpI18n::tr( 'sensor/expiring', 'secondo' );
                    break;
                case 'seconds';
                    $string = ezpI18n::tr( 'sensor/expiring', 'secondi' );
                    break;
            }
            return $string;
        };

        $format = array();
        if ( $interval->y !== 0 )
        {
            $format[] = "%y " . $translate( $interval->y, "year" );
        }
        if ( $interval->m !== 0 )
        {
            $format[] = "%m " . $translate( $interval->m, "month" );
        }
        if ( $interval->d !== 0 )
        {
            $format[] = "%d " . $translate( $interval->d, "day" );
        }
        if ( $interval->h !== 0 )
        {
            $format[] = "%h " . $translate( $interval->h, "hour" );
        }
        if ( $interval->i !== 0 )
        {
            $format[] = "%i " . $translate( $interval->i, "minute" );
        }
        if ( $interval->s !== 0 )
        {
            if ( !count( $format ) )
            {
                return ezpI18n::tr( 'sensor/expiring', 'meno di un minuto' );
            }
            else
            {
                $format[] = "%s " . $translate( $interval->s, "second" );
            }
        }

        // We use the two biggest parts
        if ( count( $format ) > 1 )
        {
            $format = array_shift( $format ) . " " . ezpI18n::tr( 'sensor/expiring', 'e' ) . " " . array_shift( $format );
        }
        else
        {
            $format = array_pop( $format );
        }

        $result = new DateDiffResult();
        $result->interval = $interval;
        $result->format = $format;
        return $result;
    }
}