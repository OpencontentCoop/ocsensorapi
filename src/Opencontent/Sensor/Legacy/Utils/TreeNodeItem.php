<?php

namespace Opencontent\Sensor\Legacy\Utils;

use eZContentObjectTreeNode;
use eZContentObjectAttribute;
use eZGmapLocation;

class TreeNodeItem implements \JsonSerializable
{
    protected $name;

    protected $id;

    protected $node_id;

    protected $type;

    protected $geo;

    protected $bounding_box;

    protected $reference;

    protected $group;

    protected $level;

    protected $can_remove;

    protected $can_edit;

    protected $can_create;

    protected $languages;

    protected $disabled_relations;

    protected $is_enabled;

    private $parent;

    /**
     * @var TreeNodeItem[]
     */
    protected $children;

    protected static $allChildren = [];

    protected static $allUserSettings = [];

    protected static $allObjectNames = [];

    public function __construct($data = array())
    {
        $this->name = $data['name'];
        $this->id = (int)$data['id'];
        $this->node_id = (int)$data['node_id'];
        $this->type = $data['type'];
        $this->geo = $data['geo'];
        $this->bounding_box = $data['bounding_box'];
        $this->reference = $data['reference'];
        $this->group = $data['group'];
        $this->children = $data['children'];
        $this->level = $data['level'];
        $this->can_remove = $data['can_remove'];
        $this->can_edit = $data['can_edit'];
        $this->can_create = $data['can_create'];
        $this->languages = $data['languages'];
        $this->disabled_relations = isset($data['disabled_relations']) ? $data['disabled_relations'] : [];
        $this->is_enabled = isset($data['is_enabled']) ? $data['is_enabled'] : true;
    }

    public static function walk(eZContentObjectTreeNode $node, $parameters = array(), $level = -1, $rootNodeId = false)
    {
        if (!$rootNodeId){
            $rootNodeId = $node->attribute('node_id');
        }
        $canCreate = $canRemove = $canEdit = true;
        if (in_array($node->attribute('class_identifier'), ['sensor_area', 'sensor_category'])) {
            $availableLanguages = $node->object()->availableLanguages();
            if ($node->attribute('class_identifier') == 'sensor_area') {
                $canCreate = $canRemove = $canEdit = in_array(\eZLocale::currentLocaleCode(), $availableLanguages);
            }
            if ($node->attribute('class_identifier') == 'sensor_category') {
                $canCreate = $canRemove = in_array(\eZLocale::currentLocaleCode(), $availableLanguages);
            }
        }

        if ($level < 0) {
            \eZDebug::writeDebug('Generate tree #' . $node->attribute('node_id'), __METHOD__);
            self::getAllChildren($node, $parameters);
        }

        $data = array();
        $data['name'] = $node->attribute('name');
        $data['id'] = (int)$node->attribute('contentobject_id');
        $data['node_id'] = (int)$node->attribute('node_id');
        $data['type'] = $node->attribute('class_identifier');
        $data['geo'] = self::geo($node);
        $data['bounding_box'] = self::boundingBox($node);
        $data['reference'] = self::reference($node);
        $data['group'] = self::group($node);
        $data['level'] = $level;
        $data['can_remove'] = $node->canRemove() && $canRemove;
        $data['can_edit'] = $node->canEdit() && $canEdit;
        $data['can_create'] = $node->canCreate() && $canCreate;
        $data['languages'] = $node->object()->availableLanguages();
        $level++;
        $data['children'] = self::children($node, $parameters, $level, $rootNodeId);
        $data['disabled_relations'] = self::disabledRelations($node);
        $data['is_enabled'] = self::isEnabled($node);
        return new TreeNodeItem($data);
    }

    private static function getAllChildren(eZContentObjectTreeNode $node, $parameters = array())
    {
        if (!isset(self::$allChildren[$node->attribute('node_id')])) {

            $userIdList = [];
            $nameIdList = [];

            /** @var $tree eZContentObjectTreeNode[] */
            if (!$parameters['classes']) {
                $tree = $node->subTree([
                    'Limitation' => [],
                    'SortBy' => $node->attribute('path_string')
                ]);
            } else {
                $tree = $node->subTree([
                    'ClassFilterType' => 'include',
                    'ClassFilterArray' => $parameters['classes'],
                    'Limitation' => [],
                    'SortBy' => $node->attribute('path_string')
                ]);
            }

            $childrenTree = [];
            foreach ($tree as $item) {
                if (!isset($childrenTree[$item->attribute('parent_node_id')])) {
                    $childrenTree[$item->attribute('parent_node_id')] = [];
                }
                $uniqueKey = $item->attribute('name') . '' . $item->attribute('contentobject_id');
                $childrenTree[$item->attribute('parent_node_id')][$uniqueKey] = $item;
                ksort($childrenTree[$item->attribute('parent_node_id')]);
                $userIdList[] = $item->attribute('contentobject_id');

                $dataMap = $item->attribute('data_map');
                if (isset($dataMap['struttura_di_competenza']) && $dataMap['struttura_di_competenza']->hasContent()) {
                    $nameIdList = array_merge_recursive($nameIdList, explode('-', $dataMap['struttura_di_competenza']->toString()));
                }

            }

            self::$allChildren[$node->attribute('node_id')] = $childrenTree;

            $db = \eZDB::instance();
            if (count($userIdList) > 0) {
                $sqlCondition = $db->generateSQLINStatement($userIdList, 'ezuser_setting.user_id', false, true, 'int');
                $userSettings = $db->arrayQuery("SELECT user_id, is_enabled FROM ezuser_setting WHERE $sqlCondition");
                $userSettingsHash = array_combine(
                    array_column($userSettings, 'user_id'),
                    array_column($userSettings, 'is_enabled')
                );
                self::$allUserSettings = $userSettingsHash + self::$allUserSettings;
            }

            if (count($nameIdList) > 0) {
                $sqlCondition = $db->generateSQLINStatement($nameIdList, 'ezcontentobject_name.contentobject_id', false, true, 'int');
                $names = $db->arrayQuery("SELECT contentobject_id, name, real_translation FROM ezcontentobject_name WHERE $sqlCondition");
                $namesHash = array_combine(
                    array_column($names, 'contentobject_id'),
                    array_column($names, 'name')
                );
                self::$allObjectNames = $namesHash + self::$allObjectNames;
            }
        }
    }

    public static function __set_state($array)
    {
        $object = new static($array);
        return $object;
    }

    protected static function group(eZContentObjectTreeNode $node)
    {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        if ($node->attribute('class_identifier') == 'sensor_category'){
            $attributeId = eZContentObjectTreeNode::classAttributeIDByIdentifier('sensor_scenario/criterion_category');
            $scenarioGroups = [];
            $params = false;
            $scenarios = $node->object()->reverseRelatedObjectList(
                false, $attributeId, false, $params
            );
            foreach ($scenarios as $scenario){
                $scenarioDataMap = $scenario->dataMap();
                if (isset($scenarioDataMap['criterion_area'])
                    && !$scenarioDataMap['criterion_area']->hasContent()
                    && isset($scenarioDataMap['owner_group'])
                    && $scenarioDataMap['owner_group']->hasContent()
                ){
                    $triggers = explode('|', $scenarioDataMap['triggers']->toString());
                    if (in_array('on_add_category', $triggers)){
                        $scenarioOwnerGroupIdList = explode('-', $scenarioDataMap['owner_group']->toString());
                        $scenarioOwnerGroupList = \OpenPABase::fetchObjects($scenarioOwnerGroupIdList);
                        $scenarioOwnerGroup = [];
                        foreach ($scenarioOwnerGroupList as $object){
                            $scenarioOwnerGroup[] = $object->attribute('name');
                        }
                        $scenarioGroups = array_merge($scenarioGroups, $scenarioOwnerGroup);
                    }
                }
            }
            return implode(', ', $scenarioGroups);
        }
//        if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
//            $idList = explode('-', $dataMap['approver']->toString());
//            if (count($idList) > 0){
//                $object = \eZContentObject::fetch((int)$idList[0]);
//                if ($object instanceof \eZContentObject){
//                    return $object->attribute('name');
//                }
//            }
//        }elseif (isset($dataMap['owner_group']) && $dataMap['owner_group']->hasContent()) {
//            $idList = explode('-', $dataMap['owner_group']->toString());
//            if (count($idList) > 0){
//                $object = \eZContentObject::fetch((int)$idList[0]);
//                if ($object instanceof \eZContentObject){
//                    return $object->attribute('name');
//                }
//            }
//        }
        if (isset($dataMap['struttura_di_competenza']) && $dataMap['struttura_di_competenza']->hasContent()) {
            $idList = explode('-', $dataMap['struttura_di_competenza']->toString());
            if (count($idList) > 0){
                if (isset(self::$allObjectNames[$idList[0]])){
                    return self::$allObjectNames[$idList[0]];
                }else {
                    return '? #' . $idList[0];
                }
            }
        }
        if (isset($dataMap['tag']) && $dataMap['tag']->hasContent()) {
            return trim($dataMap['tag']->toString());
        }

        return null;
    }

    protected static function geo(eZContentObjectTreeNode $node)
    {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        if (isset($dataMap['geo']) && $dataMap['geo']->hasContent()) {
            /** @var eZGmapLocation $content */
            $content = $dataMap['geo']->content();
            $data = array('lat' => $content->attribute('latitude'), 'lng' => $content->attribute('longitude'));
            return array(
                'id' => (int)$node->attribute('contentobject_id'),
                'coords' => array(
                    $data['lat'],
                    $data['lng']
                )
            );
        }
        return null;
    }

    protected static function boundingBox(eZContentObjectTreeNode $node)
    {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        if (isset($dataMap['bounding_box']) && $dataMap['bounding_box']->hasContent()) {
            /** @var eZGmapLocation $content */
            $content = $dataMap['bounding_box']->content();
            return $content;
        }
        return null;
    }

    protected static function reference(eZContentObjectTreeNode $node)
    {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        if (isset($dataMap['reference']) && $dataMap['reference']->hasContent()) {
            return trim($dataMap['reference']->toString());
        }
        return null;
    }

    public static function children(eZContentObjectTreeNode $node, $parameters = array(), $level = -1, $rootNodeId = false)
    {
        $data = array();
        if (isset(self::$allChildren[$rootNodeId][$node->attribute('node_id')])){
            $children = self::$allChildren[$rootNodeId][$node->attribute('node_id')];
            foreach ($children as  $child) {
                $data[] = TreeNodeItem::walk($child, $parameters, $level, $rootNodeId);
            }
        }

        return $data;
    }

    public static function disabledRelations(eZContentObjectTreeNode $node)
    {
        $list = [];
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        if ($node->attribute('class_identifier') == 'sensor_category'){
            if (isset($dataMap['disabled_areas'])){
                $stringValue = $dataMap['disabled_areas']->toString();
                $list = empty($stringValue) ? [] : explode('-', $stringValue);
            }
        }
        $list = array_map('intval', $list);
        return $list;
    }

    protected static function isEnabled(eZContentObjectTreeNode $node)
    {
        if (isset(self::$allUserSettings[$node->attribute('contentobject_id')])){
            return self::$allUserSettings[$node->attribute('contentobject_id')];
        }

        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute('data_map');
        if (isset($dataMap['avoid_assignment']) && (int)$dataMap['avoid_assignment']->attribute('data_int') === 1){
            return false;
        }

        return true;
    }

    public function attributes()
    {
        return array(
            'id',
            'name',
            'node_id',
            'geo',
            'bounding_box',
            'reference',
            'group',
            'children',
            'level',
            'disabled_relations',
        );
    }

    public function hasAttribute($name)
    {
        return in_array($name, $this->attributes());
    }

    /**
     * @param $name
     *
     * @return int|string|TreeNodeItem[]
     */
    public function attribute($name)
    {
        if (isset($this->{$name})){
            return $this->{$name};
        }

        return false;
    }

    public function jsonSerialize()
    {
        $data = [
            'id' => $this->id,
            'node_id' => $this->node_id,
            'type' => $this->type,
            'name' => $this->name,
            'geo' => $this->geo,
            'bounding_box' => $this->bounding_box,
            'reference' => $this->reference,
            'group' => $this->group,
            'level' => $this->level,
            'can_remove' => $this->can_remove,
            'can_edit' => $this->can_edit,
            'can_create' => $this->can_create,
            'languages' => $this->languages,
            'disabled_relations' => $this->disabled_relations,
            'is_enabled' => $this->is_enabled,
            'children' => []
        ];

        foreach ($this->children as $item) {
            $data['children'][] = $item->jsonSerialize();
        }

        return $data;
    }

    public function toArray()
    {
        $data = [];
        $data['name'] = $this->name;
        $data['id'] = $this->id;
        $data['node_id'] = $this->node_id;
        $data['type'] = $this->type;
        $data['geo'] = $this->geo;
        $data['bounding_box'] = $this->bounding_box;
        $data['reference'] = $this->reference;
        $data['group'] = $this->group;
        $data['level'] = $this->level;
        $data['can_remove'] = $this->can_remove;
        $data['can_edit'] = $this->can_edit;
        $data['can_create'] = $this->can_create;
        $data['languages'] = $this->languages;
        $data['disabled_relations'] = $this->disabled_relations;
        $data['is_enabled'] = $this->is_enabled;
        $data['children'] = [];
        foreach ($this->children as $child){
            $data['children'][] = $child->toArray();
        }

        return $data;
    }

    /**
     * @return TreeNodeItem|false
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param $id
     * @return TreeNodeItem|false
     */
    public function findById($id, TreeNodeItem $parent = null){
        if ($this->id == $id){
            if ($parent){
                $this->parent = $parent;
            }
            return $this;
        }

        foreach ($this->children as $child){
            if ($data = $child->findById($id, $this)){
                return $data;
            }
        }

        return false;
    }

}
