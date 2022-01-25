<?php

namespace Opencontent\Sensor\Legacy\Utils;

class Translator
{
    public static function translate($string, $context = 'sensor', $replacements = [], $language = null)
    {
        return \SensorTranslationHelper::instance()->translate($string, $context, $replacements, $language);
    }
}
