<?php

namespace Opencontent\Sensor\Legacy\Utils;

use eZLog;
use Psr\Log\LoggerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger implements LoggerInterface
{
    public function log($level, $message, array $context = [])
    {
        if (!empty($context)) {
            foreach ($context as $key => $value) {
                $message .= " ($key => " . $this->getStringValue($value) . ")";
            }
        }
        $varDir = \eZINI::instance()->variable('FileSettings', 'VarDir');
        $logDir = $varDir . '/log';

        if ($level !== LogLevel::DEBUG) {
            eZLog::write("[$level] $message", 'sensor.log', $logDir);
        }
    }

    private function getStringValue($value)
    {
        if (empty($value)) {
            $stringValue = '[empty]';
        } elseif (is_scalar($value) or method_exists($value, '__toString')) {
            $stringValue = $value;
        } elseif (is_array($value)) {
            $stringValue = '';
            foreach ($value as $index => $item) {
                if (!is_numeric($index)) {
                    $stringValue .= $index . ': ';
                }
                $stringValue .= $this->getStringValue($item) . ' ';
            }
        } else {
            $stringValue = json_encode($value);
        }

        return (string)$stringValue;
    }
}
