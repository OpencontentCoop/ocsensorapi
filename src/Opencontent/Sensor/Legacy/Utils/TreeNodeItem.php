<?php

namespace OpenContent\Sensor\Utils;

use eZContentObjectTreeNode;
use eZContentObjectAttribute;
use eZGmapLocation;

class TreeNodeItem
{
    protected $name;

    protected $id;

    protected $geo;

    protected $children;


    public function __construct( $data = array() )
    {
        $this->name = $data['name'];
        $this->id = $data['id'];
        $this->geo = $data['geo'];
        $this->children = $data['children'];
    }

    public static function walk( eZContentObjectTreeNode $node, $parameters = array() )
    {
        $data = array();
        $data['name'] = $node->attribute( 'name' );
        $data['id'] = $node->attribute( 'contentobject_id' );
        $data['geo'] = self::geo( $node );
        $data['children'] = self::children( $node, $parameters );
        return new TreeNodeItem( $data );
    }

    public static function __set_state( $array )
    {
        $object = new static( $array );
        return $object;
    }

    protected static function geo( eZContentObjectTreeNode $node )
    {
        /** @var eZContentObjectAttribute[] $dataMap */
        $dataMap = $node->attribute( 'data_map' );
        if ( isset( $dataMap['geo'] ) && $dataMap['geo']->hasContent() )
        {
            /** @var eZGmapLocation $content */
            $content = $dataMap['geo']->content() ;
            $data = array( 'lat' => $content->attribute( 'latitude' ), 'lng' => $content->attribute( 'longitude' ) );
            return array(
                'id' => $node->attribute( 'contentobject_id' ),
                'coords' => array(
                    $data['lat'],
                    $data['lng']
                )
            );
        }
        return null;
    }

    public static function children( eZContentObjectTreeNode $node, $parameters = array() )
    {
        $data = array();
        if ( $node->childrenCount( false ) > 0 )
        {
            if ( !$parameters['classes'] )
            {
                $children = $node->children();
            }
            else
            {
                $children = $node->subTree( array(
                    'Depth' => 1,
                    'DepthOperator' => 'eq',
                    'ClassFilterType' => 'include',
                    'ClassFilterArray' => $parameters['classes'],
                    'Limitation' => array(),
                    'SortBy' => $node->attribute( 'sort_array' )
                ) );
            }
            /** @var eZContentObjectTreeNode[] $children */
            foreach( $children as $child )
            {
                $data[] = TreeNodeItem::walk( $child, $parameters );
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
            'children'
        );
    }

    public function hasAttribute( $name )
    {
        return in_array( $name, $this->attributes() );
    }

    public function attribute( $name )
    {
        if ( $name == 'id' )
            return $this->id;
        elseif ( $name == 'name' )
            return $this->name;
        elseif ( $name == 'geo' )
            return $this->geo;
        elseif( $name == 'children' )
            return $this->children;

        return false;
    }
}