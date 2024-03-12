<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Repository;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\Sensor\OpenApi\BuildSchemaPropertyTrait;
use Opencontent\Sensor\OpenApi\ReferenceSchema;


class CategoryAdapter
{
    use BuildSchemaPropertyTrait;
    
    private $repository;

    private $payload;

    private $tenants;

    private $serviceSlug;

    private $severityMap;

    private function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public static function instance(Repository $repository): CategoryAdapter
    {
        return new CategoryAdapter($repository);
    }

    public static function buildCategorySchema(): OA\Schema
    {
        return new OA\Schema([
            'title' => 'InefficiencyApplication',
            'type' => 'object',
            'properties' => [
                'todo' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
            ],
        ]);
    }

    public function getCategories($language): array
    {
        //print_r($this->repository->getCategoriesTree());die();
        return [$language];
    }
}