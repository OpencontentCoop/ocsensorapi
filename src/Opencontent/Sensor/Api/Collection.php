<?php

namespace OpenContent\Sensor\Api;

abstract class Collection extends Exportable implements \IteratorAggregate
{
    public function getIterator()
    {
        return new \ArrayIterator( $this->toArray() );
    }

    public function first()
    {
        $items = $this->toArray();
        return array_shift( $items );
    }

    public function last()
    {
        $items = $this->toArray();
        return array_pop( $items );
    }

    public function count()
    {
        return count( $this->toArray() );
    }

    public static function fromCollection( Collection $collection )
    {
        $newCollection = new static();
        $newCollection->fromArray( $collection->toArray() );
        return $newCollection;
    }

    public function attributes()
    {
        $attributes = parent::attributes();
        return array_merge( $attributes, array_keys( $this->toArray() ) );
    }

    public function attribute( $key )
    {
        $items = $this->toArray();
        if ( isset( $items[$key] ) )
            return $items[$key];
        return parent::attribute( $key );
    }

    abstract protected function toArray();

    abstract protected function fromArray(array $data);

}