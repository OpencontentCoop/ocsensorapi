<?php
/**
 * Created by PhpStorm.
 * User: luca
 * Date: 23/11/15
 * Time: 22:44
 */

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Legacy\PostService;
use OpenContent\Sensor\Api\Values\Post;
use eZCollaborationItem;
use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZINI;
use eZLocale;
use eZContentLanguage;
use eZPersistentObject;
use OpenContent\Sensor\Api\Exception\BaseException;

class CachePostService extends PostService
{
    public function loadPost( $postId )
    {
        return $this->getCacheManager( $postId )->processCache(
            array( 'OpenContent\Sensor\Legacy\CachePostService', 'retrieveCache' ),
            array( 'OpenContent\Sensor\Legacy\CachePostService', 'generateCache' ),
            null,
            null,
            array( $postId, get_class( $this->repository ) )
        );
    }

    public function loadPostByInternalId( $postInternalId )
    {
        $this->getCollaborationItem( $postInternalId );
        if ( $this->collaborationItem instanceof eZCollaborationItem )
        {
            return $this->loadPost( $this->collaborationItem->attribute( 'data_int1' ) );
        }
        throw new BaseException( "eZCollaborationItem not found for id $postInternalId" );
    }

    public function refreshPost( Post $post )
    {
        parent::refreshPost( $post );
        self::clearCache( $post->id );
    }

    public static function retrieveCache( $file, $mtime, $args )
    {
        $post = include( $file );
        list( $postId, $repositoryClassName ) = $args;
        if ( !class_implements( $repositoryClassName, 'OpenContent\Sensor\Api\Repository' ) )
            throw new BaseException( "$repositoryClassName not valid repository class" );
        $repository = $repositoryClassName::instance();
        $service = new PostService( $repository );
        $service->setUserPostAware( $post );
        return $post;
    }

    public static function generateCache( $file, $args )
    {
        list( $postId, $repositoryClassName ) = $args;
        if ( !class_implements( $repositoryClassName, 'OpenContent\Sensor\Api\Repository' ) )
            throw new BaseException( "$repositoryClassName not valid repository class" );
        $repository = $repositoryClassName::instance();
        $service = new PostService( $repository );
        $post = $service->loadPost( $postId );
        return array( 'content'  => $post,
                      'scope'    => 'sensor-post-cache',
                      'datatype' => 'php',
                      'store'    => true );

    }

    public function clearCache( $postId )
    {
        $languages = eZContentLanguage::fetchLocaleList();
        if ( !empty( $languages ) )
        {
            $commonPath = eZDir::path( array( eZSys::cacheDirectory(), 'sensor' ) );
            $fileHandler = eZClusterFileHandler::instance();
            $commonSuffix = "post-object/" . eZDir::filenamePath( $postId );
            $fileHandler->fileDeleteByDirList( $languages, $commonPath, $commonSuffix );
        }
    }

    public function getCacheManager( $postId )
    {
        $cacheFile = $postId . '.cache';
        $language = $this->repository->getCurrentLanguage();
        $extraPath = eZDir::filenamePath( $postId );
        $cacheFilePath = eZDir::path( array( eZSys::cacheDirectory(), 'sensor', $language, 'post-object', $extraPath, $cacheFile ) );
        return eZClusterFileHandler::instance( $cacheFilePath );
    }
}