<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Legacy\PostService;
use Opencontent\Sensor\Api\Values\Post;
use eZCollaborationItem;
use eZDir;
use eZSys;
use eZClusterFileHandler;
use eZINI;
use eZLocale;
use eZContentLanguage;
use eZPersistentObject;
use Opencontent\Sensor\Api\Exception\BaseException;

class CachedPostService extends PostService
{
    public function loadPost($postId)
    {
        return $this->getCacheManager($postId)->processCache(
            function ($file, $mtime, $args) {
                $post = include($file);
                list($postId, $repositoryClassName) = $args;
                if (!class_implements($repositoryClassName, 'Opencontent\Sensor\Api\Repository'))
                    throw new BaseException("$repositoryClassName not valid repository class");
                $repository = $repositoryClassName::instance();
                $service = new PostService($repository);
                $service->setUserPostAware($post);
                return $post;
            },
            function ($file, $args) {
                list($postId, $repositoryClassName) = $args;
                if (!class_implements($repositoryClassName, 'Opencontent\Sensor\Api\Repository'))
                    throw new BaseException("$repositoryClassName not valid repository class");
                $repository = $repositoryClassName::instance();
                $service = new PostService($repository);
                $post = $service->loadPost($postId);
                return array('content' => $post,
                    'scope' => 'sensor-post-cache',
                    'datatype' => 'php',
                    'store' => true);

            },
            null,
            null,
            array($postId, get_class($this->repository))
        );
    }

    public function loadPostByInternalId($postInternalId)
    {
        $collaborationItem = $this->getCollaborationItem($postInternalId);
        if ($collaborationItem instanceof eZCollaborationItem) {
            return $this->loadPost($collaborationItem->attribute('data_int1'));
        }
        throw new BaseException("eZCollaborationItem not found for id $postInternalId");
    }

    public function refreshPost(Post $post)
    {
        parent::refreshPost($post);
        self::clearCache($post->id);
    }

    public static function clearCache($postId)
    {
        $languages = eZContentLanguage::fetchLocaleList();
        if (!empty($languages)) {
            $commonPath = eZDir::path(array(eZSys::cacheDirectory(), 'ocopendata', 'sensor'));
            $fileHandler = eZClusterFileHandler::instance();
            $commonSuffix = "post-object/" . eZDir::filenamePath($postId);
            $fileHandler->fileDeleteByDirList($languages, $commonPath, $commonSuffix);
        }
    }

    public function getCacheManager($postId)
    {
        $cacheFile = $postId . '.cache';
        $language = $this->repository->getCurrentLanguage();
        $extraPath = eZDir::filenamePath($postId);
        $cacheFilePath = eZDir::path(array(eZSys::cacheDirectory(), 'ocopendata', 'sensor', $language, 'post-object', $extraPath, $cacheFile));
        return eZClusterFileHandler::instance($cacheFilePath);
    }
}
