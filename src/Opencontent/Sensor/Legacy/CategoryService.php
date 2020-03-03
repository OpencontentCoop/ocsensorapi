<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\UnauthorizedException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Post\Field\Category;
use eZContentObject;

class CategoryService extends \Opencontent\Sensor\Core\CategoryService
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    protected $categories = [];

    /**
     * @param $categoryId
     * @return Category
     * @throws NotFoundException
     */
    public function loadCategory($categoryId)
    {
        if (isset($this->categories[$categoryId])) {
            return $this->categories[$categoryId];
        }
        try {
            $content = $this->searchOne("id = '$categoryId'");

            $category = $this->internalLoadCategory($content);
            $this->categories[$categoryId] = $category;

            return $category;
        } catch (\Exception $e) {
            throw new NotFoundException("Category $categoryId not found");
        }
    }

    /**
     * @param array $content
     * @return Category
     */
    private function internalLoadCategory(array $content)
    {
        $language = $this->repository->getCurrentLanguage();

        $category = new Category();
        $category->id = (int)$content['metadata']['id'];
        $category->name = $content['metadata']['name'][$language];
        $category->operatorsIdList = [];

        if (isset($content['data'][$language]['approver'])) {
            foreach ($content['data'][$language]['approver'] as $item) {
                if (in_array($item['classIdentifier'], \eZUser::fetchUserClassNames())) {
                    $category->operatorsIdList[] = (int)$item['id'];
                } else {
                    $category->groupsIdList[] = (int)$item['id'];
                }
            }
        }

        if (isset($content['data'][$language]['owner'])) {
            foreach ($content['data'][$language]['owner'] as $item) {
                $category->ownersIdList[] = (int)$item['id'];
            }
        }

        if (isset($content['data'][$language]['observer'])) {
            foreach ($content['data'][$language]['observer'] as $item) {
                $category->observersIdList[] = (int)$item['id'];
            }
        }

        return $category;
    }

    /**
     * @param $query
     * @param $limit
     * @param $cursor
     * @return array
     * @throws InvalidInputException
     * @throws \Opencontent\Opendata\Api\Exception\OutOfRangeException
     */
    public function loadCategories($query, $limit, $cursor)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'q = "' . $query . '"' : '';
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]");
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadCategory($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor];
    }

    /**
     * @param $payload
     * @return Category
     * @throws InvalidInputException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws UnexpectedException
     */
    public function createCategory($payload)
    {
        if (isset($payload['parent'])) {
            $parent = $this->repository->getCategoryService()->loadCategory($payload['parent']);
            $parentObject = eZContentObject::fetch($parent->id);
            if (!$parentObject instanceof eZContentObject) {
                throw new UnexpectedException("Parent category not found");
            }
            /** @var \eZContentObjectTreeNode $parentNode */
            $parentNode = $parentObject->mainNode();
            if ($parentNode->attribute('parent_node_id') != $this->repository->getCategoriesRootNode()->attribute('node_id')){
                throw new InvalidInputException("Invalid parent category id (max one recursion is allowed=");
            }
        } else {
            $parentNode = $this->repository->getCategoriesRootNode();
        }
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()) {
            throw new UnauthorizedException("Current user can not create category");
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
            ]
        ];

        $object = \eZContentFunctions::createAndPublishObject($params);

        return $this->loadCategory($object->attribute('id'));
    }

    /**
     * @param Category $category
     * @param $payload
     * @return Category
     * @throws InvalidInputException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws UnexpectedException
     */
    public function updateCategory(Category $category, $payload)
    {
        $contentObject = \eZContentObject::fetch($category->id);
        if ($contentObject instanceof eZContentObject) {
            if (!$contentObject->canEdit()){
                throw new UnauthorizedException("Current user can not update category");
            }

            if (isset($payload['parent'])) {
                $parent = $this->repository->getCategoryService()->loadCategory($payload['parent']);
                $parentObject = eZContentObject::fetch($parent->id);
                if (!$parentObject instanceof eZContentObject) {
                    throw new UnexpectedException("Parent category not found");
                }
                /** @var \eZContentObjectTreeNode $parentNode */
                $parentNode = $parentObject->mainNode();
                if ($parentNode->attribute('parent_node_id') != $this->repository->getCategoriesRootNode()->attribute('node_id')){
                    throw new InvalidInputException("Invalid parent category id (max one recursion is allowed=");
                }
            } else {
                $parentNode = $this->repository->getCategoriesRootNode();
            }

            $approvers = [];
            if (!empty($payload['operators'])) {
                $approvers = array_merge($approvers, $payload['operators']);
            }
            if (!empty($payload['groups'])) {
                $approvers = array_merge($approvers, $payload['groups']);
            }

            $attributes = [
                'name' => (string)$payload['name'],
                'approver' => implode('-', $approvers),
            ];
            if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {

                if (isset($payload['parent'])) {
                    $mainParentNodeId = $contentObject->attribute('main_parent_node_id');
                    if ($mainParentNodeId != $parentNode->attribute('node_id')) {
                        \eZContentObjectTreeNodeOperations::move($contentObject->mainNodeID(), $parentNode->attribute('node_id'));
                        \eZSearch::addObject($contentObject, true);
                    }
                }

                return $this->loadCategory($contentObject->attribute('id'));
            }
        }

        throw new UnexpectedException("Update failed");
    }

    public function removeCategory($categoryId)
    {
        // TODO: Implement removeCategory() method.
    }

    public function getClassIdentifierAsString()
    {
        return 'sensor_category';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getCategoriesRootNode()->attribute('node_id');
    }
}