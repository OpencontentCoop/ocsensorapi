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
    public static function walk( eZContentObjectTreeNode $node, $parameters = array() )
    {
        if ( !isset( $parameters['classes'] ) )
            $parameters['classes'] = null;

        $treeId = $node->attribute( 'node_id' );
        return self::getCacheManager( $treeId )->processCache(
            array( 'Opencontent\Sensor\Legacy\Utils\TreeNode', 'retrieveCache' ),
            array( 'Opencontent\Sensor\Legacy\Utils\TreeNode', 'generateCache' ),
            null,
            null,
            array( $node, $parameters )
        );
    }

    public static function retrieveCache( $file, $mtime, $args )
    {
        $tree = include( $file );
        return $tree;
    }

    public static function generateCache( $file, $args )
    {
        list( $node, $parameters ) = $args;
        $tree = TreeNodeItem::walk( $node, $parameters );
        return array( 'content'  => $tree,
                      'scope'    => 'sensor-tree-cache',
                      'datatype' => 'php',
                      'store'    => true );

    }

    public static function clearCache( $treeId )
    {
        $languages = eZContentLanguage::fetchLocaleList();
        if ( !empty( $languages ) )
        {
            $commonPath = eZDir::path( array( eZSys::cacheDirectory(), 'ocopendata', 'sensor' ) );
            $fileHandler = eZClusterFileHandler::instance();
            $commonSuffix = "tree/" . eZDir::filenamePath( $treeId );
            $fileHandler->fileDeleteByDirList( $languages, $commonPath, $commonSuffix );
        }
    }

    public static function getCacheManager( $treeId )
    {
        $cacheFile = $treeId . '.cache';
        $language = eZLocale::currentLocaleCode();
        $extraPath = eZDir::filenamePath( $treeId );
        $cacheFilePath = eZDir::path( array( eZSys::cacheDirectory(), 'ocopendata', 'sensor', $language, 'tree', $extraPath, $cacheFile ) );
        return eZClusterFileHandler::instance( $cacheFilePath );
    }

}