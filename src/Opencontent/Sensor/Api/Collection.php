<?php

namespace Opencontent\Sensor\Api;

abstract class Collection extends Exportable implements \ArrayAccess, \IteratorAggregate
{
    public function offsetExists($offset)
    {
        $data = $this->toArray();
        return isset($data[$offset]);
    }

    public function offsetGet($offset)
    {
        $data = $this->toArray();
        return $data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        // do nothing
    }

    public function offsetUnset($offset)
    {
        // do nothing
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->toArray());
    }

    public function first()
    {
        $items = $this->toArray();
        return array_shift($items);
    }

    public function last()
    {
        $items = $this->toArray();
        return array_pop($items);
    }

    public function getById($id)
    {
        $items = $this->toArray();
        foreach ($items as $item) {
            if ($item->id == $id) {
                return $item;
            }
        }

        return null;
    }

    public function count()
    {
        return count($this->toArray());
    }

    public static function fromCollection(Collection $collection)
    {
        $newCollection = new static();
        $newCollection->fromArray($collection->toArray());
        return $newCollection;
    }

    public function attributes()
    {
        $attributes = parent::attributes();
        return array_merge($attributes, array_keys($this->toArray()));
    }

    public function attribute($key)
    {
        $items = $this->toArray();
        if (isset($items[$key]))
            return $items[$key];
        return parent::attribute($key);
    }

    public function getArrayCopy()
    {
        return $this->toArray();
    }

    abstract protected function toArray();

    abstract protected function fromArray(array $data);

}