<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Group;
use eZContentObject;

class GroupService extends \Opencontent\Sensor\Core\GroupService
{
    const NAME_ATTRIBUTE_IDENTIFIER = 'titolo';

    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    protected static $cache = [];

    public function loadGroup($groupId, $limitations = null)
    {
        if (is_array($limitations) && empty($limitations) && isset(self::$cache[$groupId])){
            return self::$cache[$groupId];
        }
        try {
            $content = $this->searchOne($groupId, $limitations);
            $group = $this->internalLoadGroup($content);
            if (is_array($limitations) && empty($limitations)){
                self::$cache[$groupId] = $group;
            }

            return $group;
        } catch (\Exception $e) {
            throw new NotFoundException("Group $groupId not found");
        }
    }

    public function loadGroups($query, $limit, $cursor, $limitations = null)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'raw[meta_name_t] = ' . $query : '';
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]", $limitations);
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadGroup($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor, 'count' => $result->totalCount];
    }

    public function createGroup(array $payload)
    {
        $parentNode = $this->repository->getGroupsRootNode();
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()){
            throw new ForbiddenException("Current user can not create group");
        }
        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => $this->getClassIdentifierAsString(),
            'parent_node_id' => $parentNode->attribute('node_id'),
            'attributes' => [
                self::NAME_ATTRIBUTE_IDENTIFIER => (string)$payload['name'],
                'email' => (string)$payload['email'],
            ]
        ];

        $object = \eZContentFunctions::createAndPublishObject($params);

        return $this->loadGroup($object->attribute('id'));
    }

    public function updateGroup(Group $group, array $payload)
    {
        $contentObject = \eZContentObject::fetch($group->id);
        if ($contentObject instanceof \eZContentObject) {
            if (!$contentObject->canEdit()){
                throw new ForbiddenException("Current user can not update group");
            }
            $attributes = [
                self::NAME_ATTRIBUTE_IDENTIFIER => (string)$payload['name'],
                'email' => (string)$payload['email'],
            ];
            if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {

                return $this->loadGroup($contentObject->attribute('id'));
            }
        }

        throw new UnexpectedException("Update failed");
    }

    private function internalLoadGroup(array $content)
    {
        return self::fromResultContent($content, $this->repository);
    }

    public function getClassIdentifierAsString()
    {
        return 'sensor_group';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getGroupsRootNode()->attribute('node_id');
    }

    public static function fromResultContent($content, Repository $repository)
    {
        $group = new Group();
        $group->id = (int)$content['metadata']['id'];
        $group->name = $content['metadata']['name'][$repository->getCurrentLanguage()];
        if (isset($content['data'][$repository->getCurrentLanguage()]['email'])){
            $group->email = $content['data'][$repository->getCurrentLanguage()]['email'];
        }
        if (isset($content['data'][$repository->getCurrentLanguage()]['tag'])){
            $group->tag = $content['data'][$repository->getCurrentLanguage()]['tag'];
        }
        if (isset($content['data'][$repository->getCurrentLanguage()]['reference'])){
            $group->reference = $content['data'][$repository->getCurrentLanguage()]['reference'];
        }

        return $group;
    }
}
