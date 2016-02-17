<?php

namespace Opencontent\Sensor\Legacy\Utils;

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