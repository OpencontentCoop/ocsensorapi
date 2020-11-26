<?php

namespace Opencontent\Sensor\Api\Values;

use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Values\Post\Field\GeoLocation;

/**
 * Class PostUpdateStruct
 * @package Opencontent\Sensor\Api\Values
 */
class PostUpdateStruct
{
    /**
     * @var Post
     */
    private $post;

    /**
     * @var string
     */
    public $subject;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $privacy;

    /**
     * @var string
     */
    public $imagePath;

    /**
     * @var array[]
     */
    public $imagePaths = [];

    /**
     * @var array
     */
    public $categories;

    /**
     * @var GeoLocation
     */
    public $geoLocation;

    /**
     * @var array
     */
    public $areas;

    /**
     * @var string
     */
    public $meta;

    /**
     * @return Post
     */
    public function getPost()
    {
        return $this->post;
    }

    /**
     * @param Post $post
     */
    public function setPost($post)
    {
        $this->post = $post;
    }

    public static function fromArray(array $data)
    {
        $object = new static();
        foreach ($data as $identifier => $value) {
            if (!property_exists($object, $identifier)) {
                throw InvalidInputException("Field $identifier is invalid");
            }
            if ($identifier == 'geoLocation') {
                $object->{$identifier} = GeoLocation::fromArray($value);
            } else {
                $object->{$identifier} = $value;
            }
        }

        return $object;
    }

}