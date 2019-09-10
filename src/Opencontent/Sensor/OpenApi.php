<?php

namespace Opencontent\Sensor;

use Opencontent\Opendata\GeoJson\FeatureCollection;
use Opencontent\Sensor\Legacy\Repository;

class OpenApi
{
    public $siteUrl,
        $endpointUrl,
        $docsUrl,
        $emailSupport,
        $filePath;

    private $repository;

    public function __construct(
        Repository $repository,
        $siteUrl = 'https://www.example.org',
        $endpointUrl = 'https://www.example.org/api',
        $docsUrl = 'https://www.opencontent.it',
        $emailSupport = 'support@opencontent.it'
    )
    {
        $this->repository = $repository;
        $this->siteUrl = $siteUrl;
        $this->endpointUrl = $endpointUrl;
        $this->docsUrl = $docsUrl;
        $this->emailSupport = $emailSupport;
    }

    public function loadSchema()
    {
        return $this->getSchema()->toArray();
    }

    public function getSchema()
    {
        $builder = new OpenApi\SchemaBuilder($this);

        return $builder->build();
    }

    public function getOperations()
    {
        $schema = $this->getSchema()->toArray();

        $operations = [];
        foreach ($schema['paths'] as $pattern => $path) {
            foreach ($path as $method => $definition) {
                $operations[] = $definition['operationId'];
            }
        }

        return $operations;
    }

    public function replacePlaceholders($data)
    {
        $decode = false;
        if (is_array($data) || $data instanceof \JsonSerializable || method_exists($data, 'jsonSerialize') || $data instanceof FeatureCollection) {
            $data = json_encode($data);
            $decode = true;
        }

        $data = str_replace('_site_url_', $this->siteUrl, $data);
        $data = str_replace('_email_support_', $this->emailSupport, $data);
        $data = str_replace('_endpoint_url_', $this->endpointUrl, $data);
        $data = str_replace('_docs_url_', $this->docsUrl, $data);

        if ($decode) {
            $data = json_decode($data, true);
        }

        return $data;
    }

    /**
     * @return Repository
     */
    public function getRepository()
    {
        return $this->repository;
    }

}