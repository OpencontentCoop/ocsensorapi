<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\NotFoundException;
use Opencontent\Sensor\Api\Exception\PermissionException;
use Opencontent\Sensor\Api\Exception\UnauthorizedException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Core\UserService as UserServiceBase;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use eZUser;
use eZCollaborationItemStatus;
use SocialUser;
use eZCollaborationItemParticipantLink;
use eZContentObject;

class UserService extends UserServiceBase
{
    use ContentSearchTrait;

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var User[]
     */
    protected $users = array();

    /**
     * @param $id
     * @return Post|User
     * @throws \Exception
     */
    public function loadUser($id)
    {
        if (!isset($this->users[$id])) {
            $user = new User();
            $user->id = $id;
            $ezUser = $this->getEzUser($id);
            if ($ezUser instanceof eZUser) {
                $userObject = $ezUser->contentObject();
                if ($userObject instanceof \eZContentObject) {
                    $user->email = $ezUser->Email;
                    $user->name = $userObject->name(false, $this->repository->getCurrentLanguage());
                    $user->description = $this->loadUserDescription($userObject);
                    $user->fiscalCode = $this->loadUserFiscalCode($userObject);
                    $user->isEnabled = $ezUser->isEnabled();
                    $socialUser = \SensorUserInfo::instance($ezUser);
                    $user->behalfOfMode = $socialUser->hasCanBehalfOfMode();
                    $user->commentMode = !$socialUser->hasDenyCommentMode();
                    $user->moderationMode = $socialUser->hasModerationMode();
                    $user->type = $userObject->attribute('class_identifier');
                    $user->groups = $this->loadUserGroups($userObject);
                }
            }
            $this->users[$id] = $user;
        }
        return $this->users[$id];
    }

    public function loadUsers($query, $limit, $cursor)
    {
        if ($limit > \Opencontent\Sensor\Api\SearchService::MAX_LIMIT) {
            throw new InvalidInputException('Max limit allowed is ' . \Opencontent\Sensor\Api\SearchService::MAX_LIMIT);
        }

        $searchQuery = $query ? 'q = \'' . addcslashes($query, "')([]") . '\' and ': '';
        $limitations = $this->repository->getCurrentUser()->behalfOfMode ? [] : null;
        $result = $this->search("$searchQuery sort [name=>asc] limit $limit cursor [$cursor]", $limitations);
        $items = [];
        foreach ($result->searchHits as $item) {
            $items[$item['metadata']['id']] = $this->loadUser($item['metadata']['id']);
        }

        return ['items' => array_values($items), 'next' => $result->nextCursor, 'current' => $result->currentCursor, 'count' => $result->totalCount];
    }

    public function createUser(array $payload)
    {
        $ini = \eZINI::instance();
        $parentNodeId = (int)$ini->variable( "UserSettings", "DefaultUserPlacement" );
        $parentNode = \eZContentObjectTreeNode::fetch($parentNodeId);
        if (!$parentNode instanceof \eZContentObjectTreeNode || !$parentNode->canCreate()){
            throw new UnauthorizedException("Current user can not create user");
        }
        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => \eZContentClass::classIdentifierByID($ini->variable("UserSettings", "UserClassID")),
            'parent_node_id' => $parentNodeId,
            'attributes' => [
                'first_name' => (string)$payload['first_name'],
                'last_name' => (string)$payload['last_name'],
                'user_account' => $payload['email'].'|'.$payload['email'] .'||md5_password|1', // foo|foo@ez.no|1234|md5_password|0
                'fiscal_code' => (string)$payload['fiscal_code'],
            ]
        ];

        $object = \eZContentFunctions::createAndPublishObject($params);

        return $this->loadUser($object->attribute('id'));
    }

    public function updateUser(User $user, $payload)
    {
        $eZUser = $this->getEzUser($user->id);
        $contentObject = $eZUser->contentObject();
        if ($contentObject instanceof eZContentObject) {
            if (!$contentObject->canEdit()){
                throw new UnauthorizedException("Current user can not update user");
            }
            $attributes = [
                'first_name' => (string)$payload['first_name'],
                'last_name' => (string)$payload['last_name'],
                'fiscal_code' => (string)$payload['fiscal_code'],
            ];
            if (\eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])) {
                if ($payload['email'] != $user->email) {
                    $eZUser->setAttribute('email', $payload['email']);
                    $eZUser->store();
                }

                $this->refreshUser($user);

                return $this->loadUser($contentObject->attribute('id'));
            }
        }

        throw new UnexpectedException("Update failed");
    }

    public function loadFromEzUser(eZUser $ezUser)
    {
        $id = $ezUser->id();
        if (!isset($this->users[$id])) {
            $user = new User();
            $user->id = $ezUser->id();
            if ($ezUser instanceof eZUser) {
                $userObject = $ezUser->contentObject();
                if ($userObject instanceof \eZContentObject) {
                    $user->email = $ezUser->Email;
                    $user->name = $userObject->name(false, $this->repository->getCurrentLanguage());
                    $user->description = $this->loadUserDescription($userObject);
                    $user->fiscalCode = $this->loadUserFiscalCode($userObject);
                    $user->isEnabled = $ezUser->isEnabled();
                    $socialUser = $this->getSensorUser($ezUser);
                    $user->commentMode = !$socialUser->hasDenyCommentMode();
                    $user->moderationMode = $socialUser->hasModerationMode();
                    $user->type = $userObject->attribute('class_identifier');
                    $user->groups = $this->loadUserGroups($userObject);
                }
            }
            $this->users[$id] = $user;
        }
        return $this->users[$id];
    }

    private function loadUserGroups(eZContentObject $contentObject)
    {
        $dataMap = $contentObject->dataMap();
        if (isset($dataMap[OperatorService::GROUP_ATTRIBUTE_IDENTIFIER]) && $dataMap[OperatorService::GROUP_ATTRIBUTE_IDENTIFIER]->hasContent()){
            $idList = explode('-', $dataMap[OperatorService::GROUP_ATTRIBUTE_IDENTIFIER]->toString());
            $idList = array_map('intval', $idList);
            return $idList;
        }

        return [];
    }
    
    private function loadUserFiscalCode(eZContentObject $contentObject)
    {
        $dataMap = $contentObject->dataMap();
        $attributeIdentifier = 'fiscal_code';
        if (isset($dataMap[$attributeIdentifier]) && $dataMap[$attributeIdentifier]->hasContent()) {
            return $dataMap[$attributeIdentifier]->content();
        }

        return '';
    }

    private function loadUserDescription(eZContentObject $contentObject)
    {
        $dataMap = $contentObject->dataMap();
        $administrativeRole = isset($dataMap['ruolo']) && $dataMap['ruolo']->hasContent() ? trim($dataMap['ruolo']->toString()) : '';
        $administrativeLocations = [];
        $attributeIdentifier = OperatorService::GROUP_ATTRIBUTE_IDENTIFIER;
        if (isset($dataMap[$attributeIdentifier]) && $dataMap[$attributeIdentifier]->hasContent()) {
            $idList = explode('-', $dataMap[$attributeIdentifier]->toString());
            /** @var eZContentObject[] $objects */
            $objects = eZContentObject::fetchIDArray($idList);
            foreach ($objects as $object) {
                $administrativeLocations[] = $object->name(
                    false,
                    $this->repository->getCurrentLanguage()
                );
            }
        }

        return trim($administrativeRole . ' ' . implode(', ', $administrativeLocations));
    }

    public function refreshUser($user)
    {
        eZContentObject::clearCache();
        unset($this->users[$user->id]);
    }

    public function setUserPostAware($user, Post $post)
    {
        if (is_numeric($user))
            $user = $this->loadUser($user);

        $itemStatus = eZCollaborationItemStatus::fetch($post->internalId, $user->id);
        if ($itemStatus instanceof eZCollaborationItemStatus) {
            $user->lastAccessDateTime = Utils::getDateTimeFromTimestamp($itemStatus->attribute('last_read'));
            $user->hasRead = $itemStatus->attribute('is_read');
        }
        $user->permissions = $this->repository->getPermissionService()->loadUserPostPermissionCollection($user, $post);
        return $user;
    }

    public function setBlockMode(User $user, $enable = true)
    {
        $socialUser = SocialUser::instance($this->loadUser($user->id)->ezUser);
        $socialUser->setBlockMode($enable);
        $user->isEnabled = $enable;
        $this->refreshUser($user);
    }

    public function setCommentMode(User $user, $enable = true)
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        $socialUser->setDenyCommentMode(!$enable);
        $user->commentMode = $enable;
        $this->refreshUser($user);
    }

    public function setBehalfOfMode(User $user, $enable = true)
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        $socialUser->setCanBehalfOfMode($enable);
        $user->behalfOfMode = $enable;
        $this->refreshUser($user);
    }

    public function getAlerts(User $user)
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        return $socialUser->attribute('alerts');
    }

    public function addAlert(User $user, $message, $level)
    {
        $socialUser = SocialUser::instance($this->getEzUser($user->id));
        $socialUser->addFlashAlert($message, $level);
    }

    public function getEzUser($id)
    {
        $user = eZUser::fetch($id);
        if (!$user instanceof eZUser)
            $user = new eZUser(array(['contentobject_id' => 0]));
        return $user;
    }

    public function getSensorUser($id)
    {
        if (!$id instanceof eZUser){
            $id = $this->getEzUser($id);
        }
        return SocialUser::instance($id);
    }

    public function setLastAccessDateTime(User $user, Post $post)
    {
        $timestamp = time() + 1;
        eZCollaborationItemStatus::setLastRead($post->internalId, $user->id, $timestamp);
        eZCollaborationItemParticipantLink::setLastRead($post->internalId, $user->id, $timestamp);
    }

    public function getClassIdentifierAsString()
    {
        return 'user';
    }

    public function getSubtreeAsString()
    {
        return $this->repository->getUserRootNode()->attribute('node_id');
    }
}
