<?php

namespace Opencontent\Sensor\Api\Values\Post\Field;

use Opencontent\Sensor\Api\Values\Post\Field;

/**
 * Class GeoLocation
 * @package Opencontent\Sensor\Api\Values\Post\Field
 */
class GeoLocation extends Field
{
    /**
     * @var float
     */
    public $latitude;

    /**
     * @var float
     */
    public $longitude;

    /**
     * @var string
     */
    public $address;

    public static function fromArray(array $data = null)
    {
        $object = new self();
        foreach ((array)$data as $identifier => $value) {
            if (!property_exists($object, $identifier)) {
                throw InvalidInputException("Field $identifier is invalid");
            }
            $object->{$identifier} = $value;
        }

        return $object;
    }

    public function jsonSerialize()
    {
        if ($this->latitude === null && $this->longitude === null && $this->address === null){
            return null;
        }

        return parent::jsonSerialize();
    }

    public function __toString()
    {
        if ($this->latitude && $this->longitude)
            return "1|#{$this->latitude}|#{$this->longitude}|#{$this->address}";

        return '';
    }
}