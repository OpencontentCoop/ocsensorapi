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

    protected $group;

    protected $level;

    protected $can_remove;

    protected $can_edit;

    protected $can_create;

    protected $languages;

    /**
     * @var TreeNodeItem[]
     */
    protected $children;


    public function __construct($data = array())
    {
        $this->name = $data['name'];
        $this->id = (int)$data['id'];
        $this->node_id = (int)$data['node_id'];
        $this->type = $data['type'];
        $this->geo = $data['geo'];
        $this->bounding_box = $data['bounding_box'];
        $this->group = $data['group'];
        $this->children = $data['children'];
        $this->level = $data['level'];
        $this->can_remove = $data['can_remove'];
        $this->can_edit = $data['can_edit'];
        $this->can_create = $data['can_create'];
        $this->languages = $data['languages'];
    }

    public static function walk(eZContentObjectTreeNode $node, $parameters = array(), $level = -1)
    {
        $data = array();
        $data['name'] = $node->attribute('name');
        $data['id'] = (int)$node->attribute('contentobject_id');
        $data['node_id'] = (int)$node->attribute('node_id');
        $data['type'] = $node->attribute('class_identifier');
        $data['geo'] = self::geo($node);
        $data['bounding_box'] = self::boundingBox($node);
        $data['group'] = self::group($node);
        $data['level'] = $level;
        $data['can_remove'] = $node->canRemove();
        $data['can_edit'] = $node->canEdit();
        $data['can_create'] = $node->canCreate();
        $data['languages'] = $node->object()->availableLanguages();
        $level++;
        $data['children'] = self::children($node, $parameters, $level);
        return new TreeNodeItem($data);
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
        if (isset($dataMap['approver']) && $dataMap['approver']->hasContent()) {
            $idList = explode('-', $dataMap['approver']->toString());
            if (count($idList) > 0){
                $object = \eZContentObject::fetch((int)$idList[0]);
                if ($object instanceof \eZContentObject){
                    return $object->attribute('name');
                }
            }
        }
        if (isset($dataMap['struttura_di_competenza']) && $dataMap['struttura_di_competenza']->hasContent()) {
            $idList = explode('-', $dataMap['struttura_di_competenza']->toString());
            if (count($idList) > 0){
                $object = \eZContentObject::fetch((int)$idList[0]);
                if ($object instanceof \eZContentObject){
                    return $object->attribute('name');
                }
            }
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

    public static function children(eZContentObjectTreeNode $node, $parameters = array(), $level = -1)
    {
        $data = array();
        if ($node->childrenCount(false) > 0) {
            if (!$parameters['classes']) {
                $children = $node->subTree(array(
                    'Depth' => 1,
                    'DepthOperator' => 'eq',
                    'Limitation' => array(),
                    'SortBy' => $node->attribute('sort_array')
                ));
            } else {
                $children = $node->subTree(array(
                    'Depth' => 1,
                    'DepthOperator' => 'eq',
                    'ClassFilterType' => 'include',
                    'ClassFilterArray' => $parameters['classes'],
                    'Limitation' => array(),
                    'SortBy' => $node->attribute('sort_array')
                ));
            }
            /** @var eZContentObjectTreeNode[] $children */
            foreach ($children as $child) {
                $data[] = TreeNodeItem::walk($child, $parameters, $level);
            }
        }
        return $data;
    }

    public function attributes()
    {
        return array(
            'id',
            'name',
            'geo',
            'bounding_box',
            'group',
            'children',
            'level',
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
        if ($name == 'id')
            return $this->id;
        elseif ($name == 'name')
            return $this->name;
        elseif ($name == 'geo')
            return $this->geo;
        elseif ($name == 'bounding_box')
            return $this->bounding_box;
        elseif ($name == 'group')
            return $this->group;
        elseif ($name == 'children')
            return $this->children;
        elseif ($name == 'level')
            return $this->level;

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
            'group' => $this->group,
            'level' => $this->level,
            'can_remove' => $this->can_remove,
            'can_edit' => $this->can_edit,
            'can_create' => $this->can_create,
            'languages' => $this->languages,
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
        $data['group'] = $this->group;
        $data['level'] = $this->level;
        $data['can_remove'] = $this->can_remove;
        $data['can_edit'] = $this->can_edit;
        $data['can_create'] = $this->can_create;
        $data['languages'] = $this->languages;
        $data['children'] = [];
        foreach ($this->children as $child){
            $data['children'][] = $child->toArray();
        }

        return $data;
    }

}