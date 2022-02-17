<?php

namespace Opencontent\Sensor\Legacy;

use eZCollaborationItemParticipantLink;
use eZCollaborationItemStatus;
use eZContentObject;
use eZUser;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Exception\ForbiddenException;
use Opencontent\Sensor\Api\Exception\UnexpectedException;
use Opencontent\Sensor\Api\Values\Event;
use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Core\UserService as UserServiceBase;
use Opencontent\Sensor\Legacy\Utils\MailValidator;
use SocialUser;

class UserService extends UserServiceBase
{
    use ContentSearchTrait;

    const ADDITIONAL_FIELD_PREFIX = 'sensoruser_';

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var User[]
     */
    protected $users = [];

    protected $additionalInfo = [];

    private $userContentClass;

    private $firstApprovers;

    /**
     * @param $id
     * @return Post|User
     */
    public function loadUser($id)
    {
        if (!isset($this->users[$id])) {
            $user = new User();
            $user->id = $id;
            $ezUser = $this->getEzUser($id);
            if ($ezUser instanceof eZUser) {
                return $this->loadFromEzUser($ezUser);
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

    public function createUser(array $payload, $ignorePolicies = false)
    {
        $ini = \eZINI::instance();
        $parentNodeId = (int)$ini->variable( "UserSettings", "DefaultUserPlacement" );
        $parentNode = \eZContentObjectTreeNode::fetch($parentNodeId);
        if (!$parentNode instanceof \eZContentObjectTreeNode || (!$parentNode->canCreate() && $ignorePolicies === false)){
            throw new ForbiddenException("Current user can not create user");
        }

        $contentClass = $this->getUserContentClass();

        if (empty($payload['first_name'])) {
            throw new InvalidInputException("Field first_name is required");
        }
        if (empty($payload['last_name'])) {
            throw new InvalidInputException("Field last_name is required");
        }
        if (empty($payload['email'])) {
            throw new InvalidInputException("Field email is required");
        }
        if (!MailValidator::validate($payload['email'])) {
            throw new InvalidInputException("Invalid email address");
        }
        if (\eZUser::fetchByEmail($payload['email'])) {
            throw new InvalidInputException("Email address already exists");
        }
        if (isset($payload['fiscal_code']) && !empty($payload['fiscal_code'])){
            $payload['fiscal_code'] = strtoupper($payload['fiscal_code']);
            $this->assertIsValidFiscalCode($payload['fiscal_code']);
        }

        $params = [
            'creator_id' => (int)$this->repository->getCurrentUser()->id,
            'class_identifier' => $contentClass->attribute('identifier'),
            'parent_node_id' => $parentNodeId,
            'attributes' => [
                'first_name' => (string)$payload['first_name'],
                'last_name' => (string)$payload['last_name'],
                'user_account' => $payload['email'].'|'.$payload['email'] .'||md5_password|1', // foo|foo@ez.no|1234|md5_password|0
                'fiscal_code' => isset($payload['fiscal_code']) ? (string)$payload['fiscal_code'] : '',
                'phone' => isset($payload['phone']) ? (string)$payload['phone'] : '',
            ]
        ];

        $object = \eZContentFunctions::createAndPublishObject($params);

        $user = $this->loadUser($object->attribute('id'));
        if (!$user instanceof User){
            throw new \RuntimeException("Error creating new user");
        }

        $event = new Event();
        $event->identifier = 'on_create_user';
        $event->user = $user;
        $this->repository->getEventService()->fire($event);

        return $user;
    }

    public function updateUser(User $user, $payload)
    {
        $eZUser = $this->getEzUser($user->id);
        $contentObject = $eZUser->contentObject();
        if ($contentObject instanceof eZContentObject) {
            if (!$contentObject->canEdit()){
                throw new ForbiddenException("Current user can not update user");
            }
            if (\eZUser::fetchByEmail($payload['email']) && $user->email != $payload['email']) {
                throw new InvalidInputException("Email address already exists");
            }

            if (isset($payload['fiscal_code']) && strcasecmp($payload['fiscal_code'], $user->fiscalCode) !== 0){
                $payload['fiscal_code'] = strtoupper($payload['fiscal_code']);
                $this->assertIsValidFiscalCode($payload['fiscal_code']);
            }

            $attributes = [];
            if (isset($payload['first_name']) && !empty($payload['first_name'])){
                $attributes['first_name'] = (string)$payload['first_name'];
            }
            if (isset($payload['last_name']) && !empty($payload['last_name'])){
                $attributes['last_name'] = (string)$payload['last_name'];
            }
            if (isset($payload['fiscal_code']) && !empty($payload['fiscal_code'])){
                $attributes['fiscal_code'] = (string)$payload['fiscal_code'];
            }
            if (isset($payload['phone']) && !empty($payload['phone'])){
                $attributes['phone'] = (string)$payload['phone'];
            }

            if (
                \eZContentFunctions::updateAndPublishObject($contentObject, ['attributes' => $attributes])
                || (empty($attributes) && isset($payload['email']) && !empty($payload['email']))
            ) {
                if (isset($payload['email']) && $payload['email'] != $user->email) {
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
                    $user->firstName = $this->loadUserFirstName($userObject);
                    $user->lastName = $this->loadUserLastName($userObject);
                    $user->description = $this->loadUserDescription($userObject);
                    $user->fiscalCode = $this->loadUserFiscalCode($userObject);
                    $user->phone = $this->loadUserPhone($userObject);
                    $user->isEnabled = $ezUser->isEnabled();
                    $userVisitArray = \eZDB::instance()->arrayQuery("SELECT last_visit_timestamp FROM ezuservisit WHERE user_id={$ezUser->id()}");
                    if (isset($userVisitArray[0])) {
                        $user->lastAccessDateTime = Utils::getDateTimeFromTimestamp($userVisitArray[0]['last_visit_timestamp']);
                    }
                    $user->behalfOfMode = $this->loadUserCanBehalfOf($ezUser);
                    $user->commentMode = $this->loadUserCanComment($ezUser);
                    $user->moderationMode = $this->loadUserIsModerated($ezUser);
                    $user->type = $userObject->attribute('class_identifier');
                    $user->groups = $this->loadUserGroups($userObject);
                    $user->language = $this->getLocale($userObject);
                    $user->restrictMode = $this->loadUserHasRestrictMode($ezUser);

                    if ($this->firstApprovers === null) {
                        $this->firstApprovers = [];
                        foreach ($this->repository->getScenarioService()->loadInitScenarios() as $scenario) {
                            $this->firstApprovers = array_merge($this->firstApprovers, $scenario->getApprovers());
                        }
                    }
                    if (in_array($user->id, $this->firstApprovers) || !empty(array_intersect($user->groups, $this->firstApprovers))){
                        $user->isFirstApprover = true;
                    }
                }
            }
            $this->users[$id] = $user;
        }
        return $this->users[$id];
    }

    private function getLocale(eZContentObject $userObject)
    {
        $preference = $this->getEzPreferenceValue('sensor_language', (int)$userObject->attribute('id'));

        return $preference ? $preference : $userObject->attribute('initial_language_code');
    }

    private function getAdditionalInfo($userId)
    {
        if (!isset($this->additionalInfo[$userId])) {
            $name = self::ADDITIONAL_FIELD_PREFIX . $userId;
            $siteData = \eZSiteData::fetchByName($name);
            if (!$siteData instanceof \eZSiteData) {
                $row = array(
                    'name' => $name,
                    'value' => serialize(['moderate' => 0])
                );
                $siteData = new \eZSiteData($row);
                $siteData->store();
            }
            $this->additionalInfo[$userId] = unserialize($siteData->attribute('value'));
        }

        return $this->additionalInfo[$userId];
    }

    private function setAdditionalInfo($userId, $key, $value)
    {
        $name = self::ADDITIONAL_FIELD_PREFIX . $userId;
        $siteData = \eZSiteData::fetchByName($name);
        if (!$siteData instanceof \eZSiteData) {
            $row = array(
                'name' => $name,
                'value' => serialize(['moderate' => 0])
            );
            $siteData = new \eZSiteData($row);
        }
        $data = $this->getAdditionalInfo($userId);
        $data[$key] = $value;
        $siteData->setAttribute('value', serialize($data));
        $siteData->store();
        $this->additionalInfo[$userId] = $data;
    }

    private function getUserContentClass()
    {
        if ($this->userContentClass === null) {
            $this->userContentClass = \eZContentClass::fetch(\eZINI::instance()->variable("UserSettings", "UserClassID"));
        }

        return $this->userContentClass;
    }

    private function assertIsValidFiscalCode($fiscalCode)
    {
        $contentClass = $this->getUserContentClass();
        foreach ($contentClass->dataMap() as $identifier => $classAttribute){
            /** @var \eZContentClassAttribute $classAttribute */
            if ($identifier == 'fiscal_code'){
                $dataType = $classAttribute->dataType();
                if ($dataType instanceof \OCCodiceFiscaleType){
                    $fakeObjectAttribute = new \eZContentObjectAttribute([
                        'contentobject_id' => 0,
                        'contentclassattribute_id' => $classAttribute->attribute('id')
                    ]);
                    if ($dataType->validateStringHTTPInput(
                            $fiscalCode,
                            $fakeObjectAttribute,
                            $classAttribute
                        ) === \eZInputValidator::STATE_INVALID){
                        throw new InvalidInputException($fakeObjectAttribute->validationError());
                    }
                }
            }
        }
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

    private function loadUserPhone(eZContentObject $contentObject)
    {
        $dataMap = $contentObject->dataMap();
        $attributeIdentifier = 'phone';
        if (isset($dataMap[$attributeIdentifier]) && $dataMap[$attributeIdentifier]->hasContent()) {
            return $dataMap[$attributeIdentifier]->content();
        }

        return '';
    }

    private function loadUserFirstName(eZContentObject $contentObject)
    {
        $dataMap = $contentObject->dataMap();
        $attributeIdentifier = 'first_name';
        if (isset($dataMap[$attributeIdentifier]) && $dataMap[$attributeIdentifier]->hasContent()) {
            return $dataMap[$attributeIdentifier]->content();
        }

        return '';
    }

    private function loadUserLastName(eZContentObject $contentObject)
    {
        $dataMap = $contentObject->dataMap();
        $attributeIdentifier = 'last_name';
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

    private function loadUserCanBehalfOf(eZUser $user)
    {
        $result = $user->hasAccessTo( 'sensor', 'behalf' );
        return $result['accessWord'] != 'no';
    }

    private function loadUserCanComment(eZUser $user)
    {
        if ($user->isAnonymous()) {
            return false;
        }
        return $this->getEzPreferenceValue('sensor_deny_comment', $user->id()) != 1;
    }

    private function loadUserIsModerated(eZUser $user)
    {
        $info = $this->getAdditionalInfo($user->id());
        return isset($info['moderate']) && $info['moderate'] == 1;
    }

    private function loadUserHasRestrictMode(eZUser $user)
    {
        return $this->getEzPreferenceValue('sensor_restrict_access', $user->id()) == 1;
    }

    public function refreshUser($user)
    {
        eZContentObject::clearCache();
        unset($this->users[$user->id]);
        unset($this->additionalInfo[$user->id]);
        eZUser::purgeUserCacheByUserId($user->id);
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
        \eZUserOperationCollection::setSettings(  $user->id, !$enable, 0 );
        $user->isEnabled = $enable;
        $this->refreshUser($user);
    }

    public function setCommentMode(User $user, $enable = true)
    {
        if ($enable) {
            \eZPreferences::setValue('sensor_deny_comment', 1, $user->id);
        } else {
            /** @var \eZDBInterface $db */
            $db = \eZDB::instance();
            $db->query("DELETE FROM ezpreferences WHERE user_id = {$user->id} AND name = 'sensor_deny_comment'");
        }
        $user->commentMode = $enable;
        $this->refreshUser($user);
    }

    public function setBehalfOfMode(User $user, $enable = true)
    {
        $role = \eZRole::fetchByName('Sensor Assistant');
        if ($role instanceof \eZRole) {
            if ($enable) {
                $role->assignToUser($user->id);
            } else {
                $role->removeUserAssignment($user->id);
            }
        }
        $user->behalfOfMode = $enable;
        $this->refreshUser($user);
    }

    public function setModerationMode(User $user, $enable = true)
    {
        $this->setAdditionalInfo($user->id, 'moderate', intval($enable));
        $user->moderationMode = $enable;
        $this->refreshUser($user);
    }

    public function setRestrictMode(User $user, $enable = true)
    {
        if ($enable) {
            \eZPreferences::setValue('sensor_restrict_access', 1, $user->id);
        } else {
            /** @var \eZDBInterface $db */
            $db = \eZDB::instance();
            $db->query("DELETE FROM ezpreferences WHERE user_id = {$user->id} AND name = 'sensor_restrict_access'");
        }

        $user->restrictMode = $enable;
        $this->refreshUser($user);
    }

    public function getAlerts(User $user)
    {
        $messages = array();
        foreach (array('error', 'success', 'info') as $level) {
            if (\eZHTTPTool::instance()->hasSessionVariable('FlashAlert_' . $level)) {
                $messages = array_merge(
                    $messages,
                    \eZHTTPTool::instance()->sessionVariable('FlashAlert_' . $level)
                );
                \eZHTTPTool::instance()->removeSessionVariable('FlashAlert_' . $level);
            }
        }
        return $messages;
    }

    public function addAlert(User $user, $message, $level)
    {
        $messages = array();
        if (\eZHTTPTool::instance()->hasSessionVariable('FlashAlert_' . $level)) {
            $messages = \eZHTTPTool::instance()->sessionVariable('FlashAlert_' . $level);
            \eZHTTPTool::instance()->removeSessionVariable('FlashAlert_' . $level);
        }
        $messages[] = $message;
        \eZHTTPTool::instance()->setSessionVariable('FlashAlert_' . $level, $messages);
    }

    public function getEzUser($id)
    {
        $user = eZUser::fetch($id);
        if (!$user instanceof eZUser)
            $user = new eZUser(array(['contentobject_id' => 0]));
        return $user;
    }

    public function setLastAccessDateTime(User $user, Post $post)
    {
        $timestamp = time() + 1;
        if (!eZCollaborationItemStatus::fetch($post->internalId, $user->id)){
            eZCollaborationItemStatus::create($post->internalId, $user->id)->store();
        }
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

    private function getEzPreferenceValue($name, $userId)
    {
        $value = false;
        /** @var \eZDBInterface $db */
        $db = \eZDB::instance();
        $name = $db->escapeString($name);
        $existingRes = $db->arrayQuery("SELECT value FROM ezpreferences WHERE user_id = $userId AND name = '$name'");
        if (count($existingRes) == 1) {
            $value = $existingRes[0]['value'];
        }

        return $value;
    }
}
