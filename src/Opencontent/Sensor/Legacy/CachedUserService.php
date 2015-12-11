<?php

namespace OpenContent\Sensor\Legacy;

use OpenContent\Sensor\Legacy\UserService;
use OpenContent\Sensor\Api\Exception\BaseException;
use eZContentLanguage;
use eZDir;
use eZSys;
use eZClusterFileHandler;

class CachedUserService extends UserService
{
    public function loadUser( $userId )
    {
        return $this->getCacheManager( $userId )->processCache(
            array( 'OpenContent\Sensor\Legacy\CachedUserService', 'retrieveCache' ),
            array( 'OpenContent\Sensor\Legacy\CachedUserService', 'generateCache' ),
            null,
            null,
            array( $userId, get_class( $this->repository ) )
        );
    }

    public function refreshUser( \OpenContent\Sensor\Api\Values\User $user )
    {
        $this->clearCache( $user->id );
    }

    public static function retrieveCache( $file, $mtime, $args )
    {
        $user = include( $file );
        return $user;
    }

    public static function generateCache( $file, $args )
    {
        list( $userId, $repositoryClassName ) = $args;
        if ( !class_implements( $repositoryClassName, 'OpenContent\Sensor\Api\Repository' ) )
            throw new BaseException( "$repositoryClassName not valid repository class" );
        $repository = $repositoryClassName::instance();
        $service = new UserService( $repository );
        $post = $service->loadUser( $userId );
        return array( 'content'  => $post,
                      'scope'    => 'sensor-user-cache',
                      'datatype' => 'php',
                      'store'    => true );

    }

    public function clearCache( $userId )
    {
        $languages = eZContentLanguage::fetchLocaleList();
        if ( !empty( $languages ) )
        {
            $commonPath = eZDir::path( array( eZSys::cacheDirectory(), 'sensor' ) );
            $fileHandler = eZClusterFileHandler::instance();
            $commonSuffix = "user-object/" . eZDir::filenamePath( $userId );
            $fileHandler->fileDeleteByDirList( $languages, $commonPath, $commonSuffix );
        }
    }

    public function getCacheManager( $userId )
    {
        $cacheFile = $userId . '.cache';
        $language = $this->repository->getCurrentLanguage();
        $extraPath = eZDir::filenamePath( $userId );
        $cacheFilePath = eZDir::path( array( eZSys::cacheDirectory(), 'sensor', $language, 'user-object', $extraPath, $cacheFile ) );
        return eZClusterFileHandler::instance( $cacheFilePath );
    }
}