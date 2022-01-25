<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\BaseException;
use DateTime;
use DateInterval;
use ezpI18n;
use Opencontent\Sensor\Legacy\Utils\DateDiffResult;
use Opencontent\Sensor\Legacy\Utils\Translator;

class Utils
{
    /**
     * @param int $timestamp
     * @param int $intervalDays
     *
     * @return int $timestamp
     * @throws BaseException
     */
    public static function addDaysToTimeStamp($timestamp, $intervalDays)
    {
        $expiringDate = new DateTime('now', self::getDateTimeZone());
        $expiringDate->setTimestamp($timestamp);

        $expiringIntervalString = 'P' . intval($intervalDays) . 'D';
        $expiringInterval = new DateInterval($expiringIntervalString);
        if (!$expiringInterval instanceof DateInterval) {
            throw new BaseException("Invalid interval {$expiringIntervalString}");
        }
        $expiringDate->add($expiringInterval);
        return $expiringDate->format('U');
    }

    public static function getDateTimeFromTimestamp($timestamp)
    {
        $dateTime = new DateTime('now', self::getDateTimeZone());
        $dateTime->setTimestamp($timestamp);
        return $dateTime;
    }

    public static function getDateDiff($start, $end = null)
    {
        if (!$start instanceof DateTime) {
            $start = new DateTime($start, self::getDateTimeZone());
        }

        if ($end === null) {
            $end = new DateTime('now', self::getDateTimeZone());
        }

        if (!$end instanceof DateTime) {
            $end = new DateTime($start, self::getDateTimeZone());
        }

        $interval = $end->diff($start);
        $translate = function ($nb, $str) {
            $string = $nb > 1 ? $str . 's' : $str;
            switch ($string) {
                case 'year';
                    $string = Translator::translate( 'year', 'expiring');
                    break;
                case 'years';
                    $string = Translator::translate( 'years', 'expiring');
                    break;
                case 'month';
                    $string = Translator::translate( 'month', 'expiring');
                    break;
                case 'months';
                    $string = Translator::translate( 'months', 'expiring');
                    break;
                case 'day';
                    $string = Translator::translate( 'day', 'expiring');
                    break;
                case 'days';
                    $string = Translator::translate( 'days', 'expiring');
                    break;
                case 'hour';
                    $string = Translator::translate( 'hour', 'expiring');
                    break;
                case 'hours';
                    $string = Translator::translate( 'hours', 'expiring');
                    break;
                case 'minute';
                    $string = Translator::translate( 'minute', 'expiring');
                    break;
                case 'minutes';
                    $string = Translator::translate( 'minutes', 'expiring');
                    break;
                case 'second';
                    $string = Translator::translate( 'second', 'expiring');
                    break;
                case 'seconds';
                    $string = Translator::translate( 'seconds', 'expiring');
                    break;
            }
            return $string;
        };

        $format = array();
        if ($interval->y !== 0) {
            $format[] = "%y " . $translate($interval->y, "year");
        }
        if ($interval->m !== 0) {
            $format[] = "%m " . $translate($interval->m, "month");
        }
        if ($interval->d !== 0) {
            $format[] = "%d " . $translate($interval->d, "day");
        }
        if ($interval->h !== 0) {
            $format[] = "%h " . $translate($interval->h, "hour");
        }
        if ($interval->i !== 0) {
            $format[] = "%i " . $translate($interval->i, "minute");
        }
        if ($interval->s !== 0) {
            $format[] = "%s " . $translate($interval->s, "second");
        }

        // We use the two biggest parts
        if (count($format) > 1) {
            $format = array_shift($format) . " " . Translator::translate( 'and', 'expiring') . " " . array_shift($format);
        } else {
            $format = array_pop($format);
        }

        $result = new DateDiffResult();
        $result->interval = $interval;
        $result->format = $format;
        return $result;
    }

    public static function getDateIntervalSeconds(DateInterval $dateInterval)
    {
        $reference = new DateTime('now', self::getDateTimeZone());
        $endTime = clone $reference;
        $endTime = $endTime->add($dateInterval);
        return $endTime->getTimestamp() - $reference->getTimestamp();
    }

    public static function getDateTimeZone()
    {
        return new \DateTimeZone('Europe/Rome');
    }

    public static function generateDateTimeIndexes(\DateTimeInterface $dateTime)
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
        $weekNum = $weekNum == 53 ? 52 : $weekNum;
        $weekNum = $weekNum == 52 && intval($month) == 1 ? '01' : $weekNum;
        $data['week'] = $dateTime->format('Y') . $weekNum;
        $data['month'] = $dateTime->format('Ym');
        $data['quarter'] = $dateTime->format('Y') . $quarter;
        $data['semester'] = $dateTime->format('Y') . $semester;
        $data['year'] = $dateTime->format('Y');

        return $data;
    }

}
