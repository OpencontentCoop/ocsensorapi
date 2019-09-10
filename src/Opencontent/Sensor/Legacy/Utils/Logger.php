<?php

namespace Opencontent\Sensor\Legacy\Utils;

use eZLog;
use Psr\Log\LoggerInterface;
use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger implements LoggerInterface
{
    public function log($level, $message, array $context = array())
    {
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                $message .= " ($key => " . $this->getStringValue($value) . ")";
            }
        }
        eZLog::write("[$level] $message", 'sensor.log');
    }

    private function getStringValue($value)
    {
        if (is_scalar($value) or method_exists($value, '__toString')) {
            $stringValue = $value;
        }elseif (is_array($value)){
            $stringValue = '';
            foreach ($value as $index => $item){
                if (!is_numeric($index)){
                    $stringValue .= $index . ': ';
                }
                $stringValue .= $this->getStringValue($item) . ' ';
            }
        }

        return $stringValue;
    }
}