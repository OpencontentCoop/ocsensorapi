<?php

namespace Opencontent\Sensor\Legacy\Utils;

class MimeIcon
{
    public static function getIconByMimeType($mimeName, $useFullPath = true, $size = '32x32')
    {
        $wtiOperator = new \eZWordToImageOperator();
        $ini = \eZINI::instance('icon.ini');
        $repository = $ini->variable('IconSettings', 'Repository');
        $theme = $ini->variable('IconSettings', 'Theme');
        $themeINI = \eZINI::instance('icon.ini', $repository . '/' . $theme);
        $icon = $wtiOperator->iconGroupMapping($ini, $themeINI,
            'MimeIcons', 'MimeMap',
            strtolower($mimeName));
        $iconPath = '/' . $repository . '/' . $theme;
        $iconPath .= '/' . $size;
        $iconPath .= '/' . $icon;
        $siteDir = '';
        if ($useFullPath) {
            $siteDir = rtrim(str_replace('index.php', '', \eZSys::siteDir()), '\/');
        }
        return $siteDir . $iconPath;
    }

}
