<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Opendata\GeoJson\Feature;
use Opencontent\Opendata\GeoJson\FeatureCollection;
use Opencontent\Opendata\GeoJson\Geometry;
use Opencontent\Opendata\GeoJson\Properties;
use Opencontent\Sensor\Core\SearchService as BaseSearchService;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Exception;
use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use Opencontent\Opendata\Api\SearchResultDecoratorQueryBuilderAware;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchResultInfo;
use Opencontent\Opendata\Api\QueryLanguage;
use Opencontent\Sensor\Legacy\SearchService\SolrMapper;

class SearchService extends BaseSearchService
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @param $postId
     * @param array $parameters
     * @return Post
     * @throws Exception\NotFoundException
     * @throws Exception\UnexpectedException
     */
    public function searchPost($postId, $parameters = array())
    {
        //echo '<pre>';print_r($this->repository->getPostService()->loadPost($postId));die();
        $result = $this->internalSearchPosts('id = ' . $postId . ' limit 1', $parameters);
        if ($result->totalCount > 0) {
            return $result->searchHits[0];
        }

        throw new Exception\NotFoundException();
    }

    /**
     * @param $query
     * @param int $limit
     * @param string $cursor
     * @return array|mixed|SearchResults
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function searchOperatorAnGroups($query, $limit, $cursor)
    {
        $classString = $this->repository->getOperatorService()->getClassIdentifierAsString() . ',' . $this->repository->getGroupService()->getClassIdentifierAsString();
        $subtreeString = $this->repository->getOperatorService()->getSubtreeAsString() . ',' . $this->repository->getGroupService()->getSubtreeAsString();
        $searchQuery = $query ? 'raw[meta_name_t] = ' . $query : '';
        $query = "classes [{$classString}] and subtree [{$subtreeString}] and $searchQuery limit $limit cursor [$cursor]";
        $search = new ContentSearch();
        $search->setCurrentEnvironmentSettings(new \DefaultEnvironmentSettings());

        $operatorsClasses = explode(',', $this->repository->getOperatorService()->getClassIdentifierAsString());
        $groupClasses = explode(',', $this->repository->getGroupService()->getClassIdentifierAsString());

        $result = $search->search($query);
        $items = [];
        foreach ($result->searchHits as $item) {

            $data = false;

            if (in_array($item['metadata']['classIdentifier'], $operatorsClasses)){
                $data = OperatorService::fromResultContent($item, $this->repository);
            }elseif (in_array($item['metadata']['classIdentifier'], $groupClasses)){
                $data = GroupService::fromResultContent($item, $this->repository);
            }
            if ($data) {
                $items[$item['metadata']['id']] = $data;
            }
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor];
    }

    /**
     * @param $query
     * @param array $parameters
     * @param null $policies
     * @return mixed|SearchResults|FeatureCollection
     * @throws Exception\UnexpectedException
     */
    public function searchPosts($query, $parameters = array(), $policies = null)
    {
        return $this->internalSearchPosts($query, $parameters, $policies);
    }

    private function internalSearchPosts($query, $parameters = array(), $policies = null)
    {
        $parameters = array_merge([
            'executionTimes' => false,
            'readingStatuses' => false,
            'currentUserInParticipants' => false,
            'capabilities' => false,
            'format' => 'json'
        ], $parameters);

        // bootst perfomance if geojson
        if ($parameters['format'] == 'geojson'){
            $parameters['executionTimes'] = false;
            $parameters['readingStatuses'] = false;
            $parameters['capabilities'] = false;
        }

        $solrStorageTools = new \ezfSolrStorage();

        $queryBuilder = new SearchService\QueryBuilder($this->repository->getPostContentClassIdentifier());
        $queryObject = $queryBuilder->instanceQuery($query);
        $ezFindQueryObject = $queryObject->convert();

        if (!$ezFindQueryObject instanceof \ArrayObject) {
            throw new \RuntimeException("Query builder did not return a valid query");
        }

        if ($ezFindQueryObject->getArrayCopy() === array("_query" => null) && !empty($query)) {
            throw new \RuntimeException("Inconsistent query");
        }
        $ezFindQuery = $ezFindQueryObject->getArrayCopy();

        $fieldsToReturn = [$solrStorageTools->getSolrStorageFieldName(SolrMapper::SOLR_STORAGE_POST)];
        if ($parameters['executionTimes']) {
            $fieldsToReturn[] = $solrStorageTools->getSolrStorageFieldName(SolrMapper::SOLR_STORAGE_EXECUTION_TIMES);
        }
        if ($parameters['readingStatuses']) {
            $fieldsToReturn[] = $solrStorageTools->getSolrStorageFieldName(SolrMapper::SOLR_STORAGE_READ_STATUSES);
        }

        $ezFindQuery = array_merge(
            array(
                'SearchOffset' => 0,
                'SearchLimit' => ($parameters['format'] === 'geojson') ? 200 : 10,
                'Filter' => [],
            ),
            $ezFindQuery,
            array(
                'SearchContentClassID' => array($this->repository->getPostContentClass()->attribute('id')),
                'SearchSubTreeArray' => array($this->repository->getPostRootNode()->attribute('node_id')),
                'AsObjects' => false,
                //'IgnoreVisibility' => true,
                'FieldsToReturn' => $fieldsToReturn,
                'Limitation' => $policies
            )
        );

        if ($parameters['currentUserInParticipants']) {
            $currentUserFilter = "sensor_participant_id_list_lk:" . $this->repository->getCurrentUser()->id;
            if (empty($ezFindQuery['Filter'])) {
                $ezFindQuery['Filter'] = [$currentUserFilter];
            } else {
                $ezFindQuery['Filter'] = [$ezFindQuery['Filter'], $currentUserFilter];
            }
        }

        $ini = \eZINI::instance();
        $languages = $ini->variable( 'RegionalSettings', 'SiteLanguageList' );
        $languageFilter = [
            'or',
            \eZSolr::getMetaFieldName('language_code') . ':(' . implode( ' OR ' , $languages ) . ')',
            \eZSolr::getMetaFieldName( 'always_available' ) . ':true'
        ];
        if (empty($ezFindQuery['Filter'])) {
            $ezFindQuery['Filter'] = [$languageFilter];
        } else {
            $ezFindQuery['Filter'] = [$ezFindQuery['Filter'], $languageFilter];
        }

        $solr = new \eZSolr();
        $rawResults = @$solr->search(
            $ezFindQuery['query'],
            $ezFindQuery
        );

        $searchExtra = null;
        if ($rawResults['SearchExtras'] instanceof \ezfSearchResultInfo) {
            if ($rawResults['SearchExtras']->attribute('hasError')) {
                $error = $rawResults['SearchExtras']->attribute('error');
                if (is_array($error)) {
                    $error = (string)$error['msg'];
                }
                throw new \RuntimeException($error);
            }

            $searchExtra = SearchResultInfo::fromEzfSearchResultInfo($rawResults['SearchExtras']);
        }

        $searchResults = new SearchResults();

        $searchResults->totalCount = (int)$rawResults['SearchCount'];
        $searchResults->query = (string)$queryObject;

        if (($ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset']) < $searchResults->totalCount) {
            $nextPageQuery = clone $queryObject;
            $nextPageQuery->setParameter('offset', ($ezFindQuery['SearchLimit'] + $ezFindQuery['SearchOffset']));
            $searchResults->nextPageQuery = (string)$nextPageQuery;
        }

        foreach ($rawResults['SearchResult'] as $resultItem) {
            try {
                if (isset($resultItem['data_map'][SolrMapper::SOLR_STORAGE_POST])) {
                    $postSerialized = $resultItem['data_map'][SolrMapper::SOLR_STORAGE_POST];
                    $post = unserialize($postSerialized);
                    if ($parameters['executionTimes']) {
                        $post->executionTimes = $resultItem['data_map'][SolrMapper::SOLR_STORAGE_EXECUTION_TIMES];
                    }
                    if ($parameters['readingStatuses']) {
                        $post->readingStatuses = $this->getReadingStatusesForUser(
                            $resultItem['data_map'][SolrMapper::SOLR_STORAGE_READ_STATUSES],
                            $this->repository->getCurrentUser()->id
                        );
                    }
                    // bootst perfomance if geojson
                    if ($parameters['format'] != 'geojson') {
                        $this->repository->getPostService()->refreshExpirationInfo($post);
                        $this->repository->getPostService()->setCommentsIsOpen($post);
                        $this->repository->getPostService()->setUserPostAware($post);
                    }
                    if ($parameters['capabilities']) {
                        $post->capabilities = $this->repository->getPermissionService()->loadCurrentUserPostPermissionCollection($post)->getArrayCopy();
                    }
                    $searchResults->searchHits[] = $post;
                }
            } catch (\Exception $e) {
                \eZDebug::writeError($e->getMessage(), __METHOD__);
            }
        }

        if (isset($ezFindQuery['Facet'])
            && is_array($ezFindQuery['Facet'])
            && !empty($ezFindQuery['Facet'])
            && $searchExtra instanceof SearchResultInfo
        ) {
            $facets = array();
            $facetResults = $searchExtra->attribute('facet_fields');
            foreach ($ezFindQuery['Facet'] as $index => $facetDefinition) {
                $facetResult = $facetResults[$index];
                $facets[] = array(
                    'name' => $facetDefinition['name'],
                    'data' => $facetResult['countList']
                );
            }
            $searchResults->facets = $facets;
        }

        $filtersList = (array)\eZINI::instance('ezfind.ini')->variable('ExtendedAttributeFilters', 'FiltersList');
        foreach (array_keys($filtersList) as $filterId) {
            $filter = \eZFindExtendedAttributeFilterFactory::getInstance($filterId);
            if ($filter instanceof SearchResultDecoratorQueryBuilderAware) {
                $filter->setQueryBuilder($queryBuilder);
            }
            if ($filter instanceof SearchResultDecoratorInterface) {
                $filter->decorate($searchResults, $rawResults);
            }
        }

        if ($parameters['format'] === 'json'){
            return $searchResults;

        }elseif ($parameters['format'] === 'geojson'){
            $collection = new FeatureCollection();

            /** @var Post $post */
            foreach ($searchResults->searchHits as $post) {
                if ($post->geoLocation->latitude) {
                    $geometry = new Geometry();
                    $geometry->type = 'Point';
                    $geometry->coordinates = [
                        $post->geoLocation->longitude,
                        $post->geoLocation->latitude,
                    ];
                    $properties = [
                        'id' => $post->id,
                        'subject' => $post->subject,
                        'status' => $post->status,
                        'type' => $post->type,
                        'published' => $post->published->format(DATE_ISO8601),
                        'modified' => $post->modified->format(DATE_ISO8601),
                        'comment_count' => $post->comments->count(),
                        'response_count' => $post->responses->count(),
                    ];
                    $feature = new Feature($post->id, $geometry, new Properties($properties));
                    $collection->add($feature);
                }
            }

            $collection->query = $searchResults->query;
            $collection->nextPageQuery = $searchResults->nextPageQuery;
            $collection->totalCount = $searchResults->totalCount;
            $collection->facets = $searchResults->facets;

            return $collection;
        }

        throw new Exception\UnexpectedException("Invalid format " . $parameters['format']);
    }

    private function getReadingStatusesForUser($readingStatuses, $userId)
    {
        $data = [];
        foreach (array_keys(SolrMapper::getReadStatusesMap()) as $key) {
            $currentUserKey = str_replace('*', $userId, $key);
            if (isset($readingStatuses[$currentUserKey])) {
                $currentUserField = str_replace('user_' . $userId . '_', '', $currentUserKey);
                $data[$currentUserField] = $readingStatuses[$currentUserKey];
            } else {
                $emptyField = str_replace('user_*_', '', $currentUserKey);
                $data[$emptyField] = -1;
            }
        }

        return $data;
    }
}
