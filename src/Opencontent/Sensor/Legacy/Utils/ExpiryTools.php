<?php

namespace OpenContent\Sensor\Utils;


class ExpiryTools
{
    public static function addDaysToTimestamp( $creationTimestamp, $days )
    {
        $creation = new \DateTime();
        $creation->setTimestamp( $creationTimestamp );
        $creation->add( self::expiringInterval( $days ) );
        return $creation->format( 'U' );
    }

    protected static function expiringInterval( $days )
    {
        $expiringIntervalString = 'P' . intval( $days ) . 'D';
        $expiringInterval = new \DateInterval( $expiringIntervalString );
        if ( !$expiringInterval instanceof \DateInterval )
        {
            throw new \Exception( "Invalid interval {$expiringIntervalString}" );
        }
        return $expiringInterval;
    }
}