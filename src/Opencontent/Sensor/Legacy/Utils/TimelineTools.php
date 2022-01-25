<?php

namespace Opencontent\Sensor\Legacy\Utils;

use Opencontent\Sensor\Api\Values\ParticipantCollection;
use Opencontent\Sensor\Legacy\Utils\Translator;
use Opencontent\Sensor\Api\Values\Post\WorkflowStatus;

class TimelineTools
{
    public static function setText($status, $name)
    {
        if (is_array($name)) {
            $name = implode('::', $name);
        }
        $result = '';
        if ($status == WorkflowStatus::FIXED) {
            if ($name)
                $result = '_fixed by ' . $name;
            else
                $result = '_fixed';

        } elseif ($status == WorkflowStatus::READ) {
            if ($name)
                $result = '_read by ' . $name;
            else
                $result = '_read';
        } elseif ($status == WorkflowStatus::CLOSED) {
            if ($name)
                $result = '_closed by ' . $name;
            else
                $result = '_closed';
        } elseif ($status == WorkflowStatus::ASSIGNED) {
            if ($name)
                $result = '_assigned to ' . $name;
            else
                $result = '_assigned';
        } elseif ($status == WorkflowStatus::REOPENED) {
            if ($name)
                $result = '_reopened by ' . $name;
            else
                $result = '_reopened';
        }
        return $result;
    }

    public static function getType($text)
    {
        $parts = explode(' by ', $text);
        if (!isset($parts[1])) {
            $parts = explode(' to ', $text);
        }
        $type = ltrim($parts[0], '_');
        return $type;
    }

    public static function getExtra($text)
    {
        $extras = array();
        $parts = explode(' by ', $text);
        if (!isset($parts[1])) {
            $parts = explode(' to ', $text);
        }
        if (isset($parts[1])) {
            $extras = explode('::', $parts[1]);
        }
        return $extras;
    }

    public static function getText($text, ParticipantCollection $participants, $creatorId = null)
    {
        $result = $text;
        $parts = explode(' by ', $text);
        if (!isset($parts[1])) {
            $parts = explode(' to ', $text);
        }
        if (isset($parts[1])) {
            $nameParts = explode('::', $parts[1]);
            $nameString = array();
            $nameIdList = [];
            foreach ($nameParts as $namePart) {
                if (is_numeric($namePart)) {
                    $participant = $participants->getParticipantById(intval($namePart));
                    if (!$participant) {
                        $participant = $participants->getUserById(intval($namePart));
                    }
                    $nameIdList[] = $participant ? $participant->id : 0;
                    $nameString[] = $participant ? $participant->name : '?';
                } else {
                    $nameString[] = $namePart;
                }
            }
            $name = implode(', ', $nameString);

            switch ($parts[0]) {
                case '_fixed':
                    $result = Translator::translate('Fixed by %name', 'robot', ['%name' => $name]);
                    break;

                case '_read':
                    $result = Translator::translate('Read by %name', 'robot', ['%name' => $name]);
                    break;

                case '_closed':
                    $result = Translator::translate('Closed by %name', 'robot', ['%name' => $name]);
                    break;

                case '_assigned':
                    if (in_array($creatorId, $nameIdList)){
                        $result = Translator::translate('Taken in charge by %name', 'robot', ['%name' => $name]);
                    }else {
                        $result = Translator::translate('Assigned to %name', 'robot', ['%name' => $name]);
                    }
                    break;

                case '_reopened':
                    $result = Translator::translate('Reopen by %name', 'robot', ['%name' => $name]);
                    break;
            }
        } else {
            switch ($parts[0]) {
                case '_fixed':
                    $result = Translator::translate('Fixed', 'robot');
                    break;

                case '_read':
                    $result = Translator::translate('Read', 'robot');
                    break;

                case '_closed':
                    $result = Translator::translate('Closed', 'robot');
                    break;

                case '_assigned':
                    $result = Translator::translate('Assigned', 'robot');
                    break;

                case '_reopened':
                    $result = Translator::translate('Reopen', 'robot');
                    break;
            }
        }

        return $result;
    }
}
