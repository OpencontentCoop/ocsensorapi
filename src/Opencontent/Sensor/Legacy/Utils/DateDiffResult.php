<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 23/11/15
 * Time: 16:35
 */

namespace OpenContent\Sensor\Utils;

use DateInterval;

class DateDiffResult
{
    /**
     * @var DateInterval
     */
    public $interval;

    /**
     * @var string
     */
    public $format;

    public function getText()
    {
        return $this->interval->format( $this->format );
    }
}