<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\UnauthorizedException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Post\Field\Area;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;
use eZContentObject;

class AreaService extends \Opencontent\Sensor\Core\AreaService
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    public function loadArea($areaId)
    {
        try {
            $content = $this->searchOne("id = '$areaId'");

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
        $area->operatorsIdList = [];
        foreach ($content['data'][$this->repository->getCurrentLanguage()]['approver'] as $item) {
            $area->operatorsIdList[] = (int)$item['id'];
        }
        if (isset($content['data'][$this->repository->getCurrentLanguage()]['geo'])) {
            $geo = new GeoLocation();
            $geo->latitude = $content['data'][$this->repository->getCurrentLanguage()]['geo']['latitude'];
            $geo->longitude = $content['data'][$this->repository->getCurrentLanguage()]['geo']['longitude'];
            $geo->address = $content['data'][$this->repository->getCurrentLanguage()]['geo']['address'];
            $area->geo = $geo;
        }

        return $area;
    }

    public function loadAreas($query, $limit, $cursor)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'q = "' . $query . '"' : '';
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]");
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadArea($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor];
    }

    public function createArea($payload)
    {
        $parentNode = \eZContentObjectTreeNode::fetch(
            (int)$this->repository->getAreasTree()->attribute('children')[0]->attribute('node_id')
        );
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()) {
            throw new UnauthorizedException("Current user can not create category");
        }

        $geo = '';
        if (!empty($payload['geo'])) {
            $geo = GeoLocation::fromArray($payload['geo']);
        }

        $approvers = [];
        if (!empty($payload['operators'])) {
            $approvers = array_merge($approvers, $payload['operators']);
        }
        if (!empty($payload['groups'])) {
            $approvers = array_merge($approvers, $payload['groups']);
        }

        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => $this->getClassIdentifierAsString(),
            'parent_node_id' => $parentNode->attribute('node_id'),
            'attributes' => [
                'name' => (string)$payload['name'],
                'approver' => implode('-', $approvers),
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
                throw new UnauthorizedException("Current user can not update category");
            }

            $approvers = [];
            if (!empty($payload['operators'])) {
                $approvers = array_merge($approvers, $payload['operators']);
            }
            if (!empty($payload['groups'])) {
                $approvers = array_merge($approvers, $payload['groups']);
            }

            $geo = '';
            if (!empty($payload['geo'])) {
                $geo = GeoLocation::fromArray($payload['geo']);
            }

            $attributes = [
                'name' => (string)$payload['name'],
                'approver' => implode('-', $approvers),
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

    protected function getClassIdentifierAsString()
    {
        return 'sensor_area';
    }

    protected function getSubtreeAsString()
    {
        return $this->repository->getAreasRootNode()->attribute('node_id');
    }

}