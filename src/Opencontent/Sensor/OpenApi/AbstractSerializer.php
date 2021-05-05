<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\OpenApi;

abstract class AbstractSerializer
{
    /**
     * @var OpenApi
     */
    protected $apiSettings;

    /**
     * @var string[]
     */
    private $embedFields = [];

    public function __construct(OpenApi $apiSettings)
    {
        $this->apiSettings = $apiSettings;
    }

    abstract public function serialize($item, array $parameters = []);

    public function serializeItem($item, array $parameters = [])
    {
        return $this->serialize($item, $parameters);
    }

    public function serializeItems($items, array $parameters = [])
    {
        $serializedItems = [];
        foreach ($items as $item) {
            $serializedItems[] = $this->serialize($item, $parameters);
        }

        return $serializedItems;
    }

    protected function formatDate($dateTime)
    {
        if ($dateTime instanceof \DateTime) {
            return $dateTime->format('c');
        }
        return null;
    }

    /**
     * @return array
     */
    public function getEmbedFields()
    {
        return $this->embedFields;
    }

    /**
     * @param array $embedFields
     * @return PostSerializer
     */
    public function setEmbedFields($embedFields)
    {
        $this->embedFields = $embedFields;
        return $this;
    }
}