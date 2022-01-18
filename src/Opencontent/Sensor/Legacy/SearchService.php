<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Opendata\Api\ContentSearch;
use Opencontent\Opendata\Api\QueryLanguage;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchResultInfo;
use Opencontent\Opendata\Api\SearchResultDecoratorInterface;
use Opencontent\Opendata\Api\SearchResultDecoratorQueryBuilderAware;
use Opencontent\Opendata\Api\Values\SearchResults;
use Opencontent\Opendata\GeoJson\Feature;
use Opencontent\Opendata\GeoJson\FeatureCollection;
use Opencontent\Opendata\GeoJson\Geometry;
use Opencontent\Opendata\GeoJson\Properties;
use Opencontent\Sensor\Api\Exception;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Core\SearchService as BaseSearchService;
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
        if (is_numeric($postId)) {
            $result = $this->internalSearchPosts('id = ' . $postId . ' limit 1', $parameters);
        }else{
            $result = $this->internalSearchPosts('remote_id = ' . $postId . ' limit 1', $parameters);
        }
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
        $currentUser = $this->repository->getCurrentUser();
        $currentUserId = $currentUser->id;
        $parameters = array_merge([
            'executionTimes' => false,
            'readingStatuses' => false,
            'currentUserInParticipants' => false,
            'capabilities' => false,
            'format' => 'json',
            'authorFiscalCode' => false
        ], $parameters);

        if ($currentUser->restrictMode){
            $parameters['currentUserInParticipants'] = true;
        }

        if (!empty($parameters['authorFiscalCode'])) {
            $authorIdList = $this->getUserIdListByFiscalCode($parameters['authorFiscalCode']);
            if (empty($authorIdList)) {
                $searchResults = new SearchResults();
                $searchResults->totalCount = 0;

                return $searchResults;
            } else {
                $query .= ' and author_id in [' . implode(',', $authorIdList) . ']';
            }
        }

        $solrStorageTools = new \ezfSolrStorage();

        $defaultLimit = ($parameters['format'] === 'geojson') ? 1500 : self::DEFAULT_LIMIT;

        // allow empty queries
        if (empty($query)) {
            $query = 'limit ' . $defaultLimit;
        }

        // generate query from string
        $queryBuilder = new SearchService\QueryBuilder($this->repository->getPostApiClass());
        $queryObject = $queryBuilder->instanceQuery($query);
        $ezFindQueryObject = $queryObject->convert();
        if (!$ezFindQueryObject instanceof \ArrayObject) {
            throw new Exception\UnexpectedException("Query builder did not return a valid query");
        }
        if ($ezFindQueryObject->getArrayCopy() === array("_query" => null) && !empty($query)) {
            throw new Exception\UnexpectedException("Inconsistent query");
        }
        $ezFindQuery = $ezFindQueryObject->getArrayCopy();

        // boost geojson query fetching single fields
        if ($parameters['format'] == 'geojson'){
            $fieldsToReturn = [
                'sensor_coordinates_gpt',
                'sensor_type_s',
                'sensor_status_lk',
                'meta_published_dt',
                'meta_modified_dt',
                'sensor_user_' . $currentUserId. '_responses_i',
                'sensor_user_' . $currentUserId. '_comments_i',
            ];
            if (empty($ezFindQuery['Filter'])) {
                $ezFindQuery['Filter'] = ["sensor_coordinates_gpt:[-90,-90 TO 90,90]"];
            } else {
                $ezFindQuery['Filter'] = [$ezFindQuery['Filter'], "sensor_coordinates_gpt:[-90,-90 TO 90,90]"];
            }
        }else {
            $fieldsToReturn = [$solrStorageTools->getSolrStorageFieldName(SolrMapper::SOLR_STORAGE_POST)];
            if ($parameters['executionTimes']) {
                $fieldsToReturn[] = $solrStorageTools->getSolrStorageFieldName(SolrMapper::SOLR_STORAGE_EXECUTION_TIMES);
            }
            if ($parameters['readingStatuses']) {
                $fieldsToReturn[] = $solrStorageTools->getSolrStorageFieldName(SolrMapper::SOLR_STORAGE_READ_STATUSES);
            }
        }

        $ezFindQuery = array_merge(
            array(
                'SearchOffset' => 0,
                'SearchLimit' => $defaultLimit,
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
            $currentUserFilter = "sensor_participant_id_list_lk:" . $currentUserId;
            if (empty($ezFindQuery['Filter'])) {
                $ezFindQuery['Filter'] = [$currentUserFilter];
            } else {
                $ezFindQuery['Filter'] = [$ezFindQuery['Filter'], $currentUserFilter];
            }
        }

        if ($ezFindQuery['SearchLimit'] > self::MAX_LIMIT && $parameters['format'] !== 'geojson'){
            throw new InvalidInputException('Max limit allowed is ' . self::MAX_LIMIT);
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
                throw new Exception\UnexpectedException($error);
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
                if ($parameters['format'] == 'geojson') {
                    if (isset($resultItem['fields']['sensor_coordinates_gpt'])) {
                        $points = explode(',', $resultItem['fields']['sensor_coordinates_gpt'], 2);
                        $id = (int)$resultItem['id'];
                        $status = '';
                        if (isset($resultItem['fields']['sensor_status_lk'])){
                            $status = $this->repository->getPostStatusService()->loadPostStatus(
                                $resultItem['fields']['sensor_status_lk']
                            );
                        }
                        $type = '';
                        if (isset($resultItem['fields']['sensor_type_s'])){
                            $type = $this->repository->getPostTypeService()->loadPostType(
                                $resultItem['fields']['sensor_type_s']
                            );
                        }
                        $commentCount = isset($resultItem['sensor_user_' . $currentUserId . '_comments_i']) ?
                            (int)$resultItem['sensor_user_' . $currentUserId . '_comments_i'] : 0;
                        $responseCount = isset($resultItem['sensor_user_' . $currentUserId . '_responses_i']) ?
                            (int)$resultItem['sensor_user_' . $currentUserId . '_responses_i'] : 0;

                        $geometry = new Geometry();
                        $geometry->type = 'Point';
                        $geometry->coordinates = [
                            $points[1],
                            $points[0]
                        ];
                        $feature = new Feature($id, $geometry, new Properties([
                            'id' => $id,
                            'subject' => isset($resultItem['name']) ? $resultItem['name'] : '',
                            'status' => $status,
                            'type' => $type,
                            'published' => $resultItem['published'],
                            'modified' => $resultItem['modified'],
                            'comment_count' => $commentCount,
                            'response_count' => $responseCount,
                        ]));
                        $searchResults->searchHits[] = $feature;
                    }

                }elseif (isset($resultItem['data_map'][SolrMapper::SOLR_STORAGE_POST])) {
                    $postSerialized = $resultItem['data_map'][SolrMapper::SOLR_STORAGE_POST];
                    /** @var Post $post */
                    $post = unserialize($postSerialized);
                    if ($parameters['executionTimes']) {
                        $post->executionTimes = $resultItem['data_map'][SolrMapper::SOLR_STORAGE_EXECUTION_TIMES];
                    }
                    if ($parameters['readingStatuses']) {
                        $post->readingStatuses = $this->getReadingStatusesForUser(
                            $resultItem['data_map'][SolrMapper::SOLR_STORAGE_READ_STATUSES],
                            $currentUserId
                        );
                    }
                    $this->repository->getPostService()->refreshExpirationInfo($post);
                    $this->repository->getPostService()->setCommentsIsOpen($post);
                    $this->repository->getPostService()->setUserPostAware($post);
                    if ($parameters['capabilities']) {
                        $post->capabilities = $this->repository->getPermissionService()->loadCurrentUserPostPermissionCollection($post)->getArrayCopy();
                    }
                    $post->commentsToModerate = $post->comments->commentsToModerate();
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
            $collection->features = $searchResults->searchHits;
            $collection->query = $searchResults->query;
            $collection->nextPageQuery = $searchResults->nextPageQuery;
            $collection->totalCount = $searchResults->totalCount;
            $collection->facets = $searchResults->facets;

            return $collection;
        }

        throw new Exception\UnexpectedException("Invalid format " . $parameters['format']);
    }

    private function getUserIdListByFiscalCode($fiscalCode)
    {
        $query = "select-fields [metadata.id => data.fiscal_code] and fiscal_code = '\"{$fiscalCode}\"'";
        $currentEnvironment = new \DefaultEnvironmentSettings();
        $parser = new \ezpRestHttpRequestParser();
        $request = $parser->createRequest();
        $currentEnvironment->__set('request', $request);
        $contentSearch = new ContentSearch();
        $contentSearch->setEnvironment($currentEnvironment);
        $data = (array)$contentSearch->search($query, array());

        return array_keys($data);
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
                $emptyField = str_replace('user_*_', '', $key);
                $data[$emptyField] = -1;
            }
        }

        return $data;
    }
}
