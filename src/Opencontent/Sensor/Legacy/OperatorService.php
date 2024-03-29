<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Group;
use Opencontent\Sensor\Api\Values\Operator;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use eZContentObject;

class OperatorService extends \Opencontent\Sensor\Core\OperatorService
{
    const GROUP_ATTRIBUTE_IDENTIFIER = 'struttura_di_competenza';

    const ROLE_ATTRIBUTE_IDENTIFIER = 'ruolo';

    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    protected static $firstApprovers;

    private $operatorsByGroup = [];

    public function loadOperator($id, $limitations = null)
    {
        try {
            $content = $this->searchOne($id, $limitations);

            return $this->internalLoadOperator($content);
        } catch (\Exception $e) {
            throw new NotFoundException("Operator $id not found");
        }
    }

    private static function fromUser(User $user)
    {
        $operator = new Operator();
        foreach (get_object_vars($user) as $key => $value){
            $operator->{$key} = $value;
        }

        return $operator;
    }

    private function internalLoadOperator($content)
    {
        return self::fromResultContent($content, $this->repository);
    }

    public static function fromResultContent($content, Repository $repository)
    {
        return self::fromUser($repository->getUserService()->loadUser($content['metadata']['id']));
    }

    public function loadOperators($query, $limit, $cursor, $limitations = null)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'raw[meta_name_t] = ' . $query : '';
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]", $limitations);
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadOperator($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor, 'count' => $result->totalCount];
    }

    public function loadOperatorsByGroup(Group $group, $limit, $cursor, $limitations = null)
    {
        if (!isset($this->operatorsByGroup[$group->id])) {
            $this->operatorsByGroup[$group->id] = [];
            $contentObject = eZContentObject::fetch($group->id);
            if ($contentObject instanceof eZContentObject) {
                $classAttribute = 'sensor_operator/' . self::GROUP_ATTRIBUTE_IDENTIFIER;
                $attributeID = \eZContentObjectTreeNode::classAttributeIDByIdentifier($classAttribute);
                $reverseRelated = $contentObject->reverseRelatedObjectList(false, $attributeID, true);
                foreach ($reverseRelated as $operators) {
                    foreach ($operators as $operator) {
                        $this->operatorsByGroup[$group->id][] = self::fromUser($this->repository->getUserService()->loadUser($operator->attribute('id')));
                    }
                }
            }
        }
        return ['items' => $this->operatorsByGroup[$group->id], 'next' => null, 'current' => null, 'count' => count($this->operatorsByGroup[$group->id])];
    }

    public function searchOperatorsByGroup(Group $group, $limit, $cursor, $limitations = null)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $groupQuery = self::GROUP_ATTRIBUTE_IDENTIFIER . '.id = ' . $group->id;
        $result = $this->search("$groupQuery sort [name=>desc] limit $limit cursor [$cursor]", $limitations);
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->internalLoadOperator($item);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor, 'count' => $result->totalCount];
    }

    public function createOperator(array $payload)
    {
        $parentNode = $this->repository->getOperatorsRootNode();
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()){
            throw new ForbiddenException("Current user can not create operator");
        }
        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => $this->getClassIdentifierAsString(),
            'parent_node_id' => $parentNode->attribute('node_id'),
            'attributes' => [
                'name' => (string)$payload['name'],
                'e_mail' => (string)$payload['email'],
                'account' => $payload['email'].'|'.$payload['email'] .'||md5_password|1', // foo|foo@ez.no|1234|md5_password|0
                self::ROLE_ATTRIBUTE_IDENTIFIER => (string)$payload['role'],
                self::GROUP_ATTRIBUTE_IDENTIFIER => implode('-', $payload['groups']),
            ]
        ];

        $object = \eZContentFunctions::createAndPublishObject($params);

        return self::fromUser($this->repository->getUserService()->loadUser($object->attribute('id')));
    }

    public function updateOperator(Operator $operator, array $payload)
    {
        $eZUser = $this->repository->getUserService()->getEzUser($operator->id);
        $contentObject = $eZUser->contentObject();
        if ($contentObject instanceof eZContentObject) {
            if (!$contentObject->canEdit()){
                throw new ForbiddenException("Current user can not update operator");
            }
            $attributes = [
                'name' => (string)$payload['name'],
                self::ROLE_ATTRIBUTE_IDENTIFIER => (string)$payload['role'],
                'e_mail' => (string)$payload['email'],
                self::GROUP_ATTRIBUTE_IDENTIFIER => implode('-', $payload['groups']),
            ];
            if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {
                if ($payload['email'] != $operator->email) {
                    $eZUser->setAttribute('email', $payload['email']);
                    $eZUser->store();
                }

                $this->repository->getUserService()->refreshUser($operator);

                return self::fromUser($this->repository->getUserService()->loadUser($contentObject->attribute('id')));
            }
        }

        throw new UnexpectedException("Update failed");
    }

    public function getClassIdentifierAsString()
    {
        return 'sensor_operator';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getAreasRootNode()->attribute('node_id');
    }
}
