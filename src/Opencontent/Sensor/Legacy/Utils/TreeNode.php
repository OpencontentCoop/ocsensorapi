<?php

namespace Opencontent\Sensor\Legacy\Utils;

use eZContentObjectTreeNode;
use eZContentLanguage;
use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZLocale;

class TreeNode
{
    /**
     * @param eZContentObjectTreeNode $node
     * @param array $parameters
     *
     * @return TreeNodeItem
     */
    public static function walk(eZContentObjectTreeNode $node, $parameters = array())
    {
        if (!isset($parameters['classes']))
            $parameters['classes'] = null;

        $language = isset($parameters['language']) ? $parameters['language'] : false;

        return self::getCacheManager($node, $language)->processCache(
            function ($file, $mtime, $args) {
                $tree = include($file);
                return $tree;
            },
            function ($file, $args) {
                list($node, $parameters) = $args;
                $tree = TreeNodeItem::walk($node, $parameters);
                return array('content' => $tree,
                    'scope' => 'sensor-tree-cache',
                    'datatype' => 'php',
                    'store' => true);

            },
            null,
            null,
            array($node, $parameters)
        );
    }

    public static function clearCache($treeId)
    {
        $languages = eZContentLanguage::fetchLocaleList();
        if (!empty($languages)) {
            $commonPath = eZDir::path(array(eZSys::cacheDirectory(), 'ocopendata', 'sensor'));
            $fileHandler = eZClusterFileHandler::instance();
            $commonSuffix = "tree/" . eZDir::filenamePath($treeId);
            $fileHandler->fileDeleteByDirList($languages, $commonPath, $commonSuffix);
        }
    }

    protected static function getCacheManager(eZContentObjectTreeNode $treeNode, $language = false)
    {
        $treeId = $treeNode->attribute('node_id');
        $userRoleIdList = [(int)$treeNode->canCreate(), (int)$treeNode->canEdit(), (int)$treeNode->canRemove()];
        $cacheFile = 'tree_' . $treeId . '_' . implode('_', $userRoleIdList) . '.cache';
        $language = $language ?: eZLocale::currentLocaleCode();
        $extraPath = eZDir::filenamePath($treeId);
        $cacheFilePath = eZDir::path(array(eZSys::cacheDirectory(), 'ocopendata', 'sensor', $language, 'tree', $extraPath, $cacheFile));
        return eZClusterFileHandler::instance($cacheFilePath);
    }

}
