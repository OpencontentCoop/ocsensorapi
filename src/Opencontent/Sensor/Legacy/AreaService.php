<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\GeoBounding;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;
use eZContentObject;
use Location\Coordinate;
use Location\Polygon;
use Opencontent\Sensor\Legacy\Utils\TreeNode;

class AreaService extends \Opencontent\Sensor\Core\AreaService
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    private $polygons;

    public function loadArea($areaId, $limitations = null)
    {
        try {
            $content = $this->searchOne("id = '$areaId'", $limitations);

            return $this->internalLoadArea($content);
        } catch (\Exception $e) {
            throw new NotFoundException("Area $areaId not found");
        }
    }

    private function internalLoadArea(array $content)
    {
        $area = new Area();
        $area->id = (int)$content['metadata']['id'];
        $area->name = $content['metadata']['name'][$this->repository->getCurrentLanguage()];
        
        if (isset($content['data'][$this->repository->getCurrentLanguage()]['geo'])) {
            $geo = new GeoLocation();
            $geo->latitude = $content['data'][$this->repository->getCurrentLanguage()]['geo']['latitude'];
            $geo->longitude = $content['data'][$this->repository->getCurrentLanguage()]['geo']['longitude'];
            $geo->address = $content['data'][$this->repository->getCurrentLanguage()]['geo']['address'];
            $area->geo = $geo;
        }

        if (isset($content['data'][$this->repository->getCurrentLanguage()]['bounding_box']['geo_json'])) {
            $area->geoBounding = new GeoBounding($content['data'][$this->repository->getCurrentLanguage()]['bounding_box']);
        }

        return $area;
    }

    public function loadAreas($query, $limit, $cursor, $excludeMainArea = false)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'q = "' . $query . '" and' : '';
        if ($excludeMainArea) {
            $searchQuery .= ' raw[meta_main_parent_node_id_si] != ' . $this->repository->getRootNode()->attribute('node_id');
        }
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]");
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadArea($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor, 'count' => $result->totalCount];
    }

    public function createArea($payload)
    {
        $parentNode = \eZContentObjectTreeNode::fetch(
            (int)$this->repository->getAreasTree()->attribute('children')[0]->attribute('node_id')
        );
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()) {
            throw new ForbiddenException("Current user can not create category");
        }

        $geo = '';
        if (!empty($payload['geo'])) {
            $geo = GeoLocation::fromArray($payload['geo']);
        }

        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => $this->getClassIdentifierAsString(),
            'parent_node_id' => $parentNode->attribute('node_id'),
            'attributes' => [
                'name' => (string)$payload['name'],
                'geo' => (string)$geo,
            ]
        ];

        $object = \eZContentFunctions::createAndPublishObject($params);

        return $this->loadArea($object->attribute('id'));
    }

    public function updateArea(Area $area, $payload)
    {
        $contentObject = \eZContentObject::fetch($area->id);
        if ($contentObject instanceof eZContentObject) {
            if (!$contentObject->canEdit()){
                throw new ForbiddenException("Current user can not update category");
            }

            $geo = '';
            if (!empty($payload['geo'])) {
                $geo = GeoLocation::fromArray($payload['geo']);
            }

            $attributes = [
                'name' => (string)$payload['name'],
                'geo' => (string)$geo,
            ];
            if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {

                return $this->loadArea($contentObject->attribute('id'));
            }
        }

        throw new UnexpectedException("Update failed");
    }

    public function removeArea($areaId)
    {
        // TODO: Implement removeArea() method.
    }

    public function getClassIdentifierAsString()
    {
        return 'sensor_area';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getAreasRootNode()->attribute('node_id');
    }

    public function findAreaByGeoLocation(GeoLocation $geoLocation)
    {
        if ($this->polygons === null) {
            $areas = $this->repository->getAreasTree()->toArray();
            $this->polygons = [];
            foreach ($areas['children'][0]['children'] as $area) {
                $geoJson = json_decode($area['bounding_box']['geo_json'], true);
                $this->polygons[$area['id']] = [];
                foreach ($geoJson['features'] as $feature) {
                    if ($feature['geometry']['type'] === 'Polygon' || $feature['geometry']['type'] === 'MultiPolygon') {
                        $geofence = new Polygon();
                        foreach ($feature['geometry']['coordinates'] as $coordinates) {
                            foreach ($coordinates as $coordinate) {
                                if (is_numeric($coordinate[0])) { //Polygon
                                    $geofence->addPoint(new Coordinate($coordinate[1], $coordinate[0]));
                                } else { // MultiPolygon
                                    foreach ($coordinate as $coordinate_) {
                                        $geofence->addPoint(new Coordinate($coordinate_[1], $coordinate_[0]));
                                    }
                                }
                            }
                        }
                        $this->polygons[(int)$area['id']][] = $geofence;
                    }
                }
            }
        }
        $point = new Coordinate($geoLocation->latitude, $geoLocation->longitude);
        foreach ($this->polygons as $areaId => $geofences){
            foreach ($geofences as $geofence){
                if ($geofence->contains($point)){
                    return $this->loadArea($areaId, []);
                }
            }
        }

        return null;
    }

    public function disableCategories(Area $area, $categoryIdList)
    {
        $contentObject = \eZContentObject::fetch((int)$area->id);
        if ($contentObject instanceof eZContentObject && !$contentObject->canEdit()) {
            throw new ForbiddenException("Current user can not update area");
        }

        $needClearCache = false;
        $categories = $this->repository->getCategoryService()->loadAllCategories();
        foreach ($categories as $category) {
            $object = eZContentObject::fetch((int)$category->id);
            $dataMap = $object->dataMap();
            if (isset($dataMap['disabled_areas'])) {
                $stringValue = $dataMap['disabled_areas']->toString();
                $idList = array_fill_keys(explode('-', $stringValue), true);
                unset($idList['']);
                ksort($idList);
                if (in_array($category->id, $categoryIdList)) {
                    $idList[$area->id] = true;
                } else {
                    unset($idList[$area->id]);
                }
                $newStringValue = implode('-', array_keys($idList));
                if ($stringValue != $newStringValue) {
                    $dataMap['disabled_areas']->fromString($newStringValue);
                    $dataMap['disabled_areas']->store();
                    $this->repository->getLogger()->debug(
                        'Update category disable zones',
                        ['category' => $category->id, 'area' => $area->id]
                    );
                    $needClearCache = true;
                }
            }
        }
        if ($needClearCache) {
            TreeNode::clearCache($this->repository->getCategoriesRootNode()->attribute('node_id'));
        }
        return true;
    }
}