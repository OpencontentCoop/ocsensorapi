<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Repository;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\Sensor\Legacy\Utils\TreeNode;
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
        $item = [
            'type' => 'object',
            'properties' => [
                'value' => self::buildSchemaProperty(['type' => 'string']),
                'label' => self::buildSchemaProperty(['type' => 'string']),
            ],
        ];

        return new OA\Schema([
            'title' => 'InefficiencyCategories',
            'type' => 'object',
            'properties' => [
                'it' => self::buildSchemaProperty(['type' => 'array', 'items' => $item]),
                'de' => self::buildSchemaProperty(['type' => 'array', 'items' => $item]),
                'en' => self::buildSchemaProperty(['type' => 'array', 'items' => $item]),
            ],
        ]);
    }

    public function getCategories($area = null): array
    {
        $categoryClassId = (int)\eZContentClass::classIDByIdentifier('sensor_category');
        $disallowZoneAttributeId = (int)\eZContentClassAttribute::classAttributeIDByIdentifier('sensor_category/disabled_areas');
        $it = (int)\eZContentLanguage::idByLocale('ita-IT');
        $de = (int)\eZContentLanguage::idByLocale('ger-DE');
        $en = (int)\eZContentLanguage::idByLocale('eng-GB');
        $query = "SELECT 
                    ezcontentobject.id as value, 
                    MAX(ezcontentobject_name.name) FILTER (WHERE ezcontentobject_name.language_id & $it > 0) AS it,
                    MAX(ezcontentobject_name.name) FILTER (WHERE ezcontentobject_name.language_id & $de > 0) AS de,
                    MAX(ezcontentobject_name.name) FILTER (WHERE ezcontentobject_name.language_id & $en > 0) AS en,
                    array_agg(DISTINCT ezcontentobject_link.to_contentobject_id) as disallow_zones
                        FROM ezcontentobject
                            INNER JOIN ezcontentobject_name 
                            ON ( ezcontentobject.id = ezcontentobject_name.contentobject_id 
                                AND ezcontentobject.current_version = ezcontentobject_name.content_version )
                            LEFT JOIN ezcontentobject_link
                            ON ( ezcontentobject.id = ezcontentobject_link.from_contentobject_id  
                                AND  ezcontentobject.current_version =  ezcontentobject_link.from_contentobject_version 
                                AND ezcontentobject_link.contentclassattribute_id = $disallowZoneAttributeId)
                        WHERE ezcontentobject.contentclass_id  IN  ( $categoryClassId ) 
                        GROUP BY ezcontentobject.id
                        ORDER BY ezcontentobject.id asc";

        $zoneId = false;
        if ($area) {
            if (is_numeric($area)) {
                $zoneId = (int)$area;
            } else {
                $contentObjectIdentifier = \eZDB::instance()->escapeString($area);
                $fetchSQLString = "SELECT ezcontentobject.id FROM ezcontentobject WHERE ezcontentobject.remote_id='$contentObjectIdentifier'";
                $resArray = \eZDB::instance()->arrayQuery($fetchSQLString);
                $zoneId = isset($resArray[0]['id']) ? (int)$resArray[0]['id'] : false;
            }
        }

        if ($zoneId) {
            $query = "WITH cats AS ($query), exclude_cats AS (SELECT * FROM cats where $zoneId = ANY(disallow_zones))
                SELECT value, it, de, en FROM cats WHERE value not in (SELECT value FROM exclude_cats)";
        } else {
            $query = "WITH cats AS ($query) SELECT value, it, de, en FROM cats";
        }

        $rows = \eZDB::instance()->arrayQuery($query);
        $base = array_combine(array_column($rows, 'value'), array_column($rows, 'it'));
        $categories = [];
        $languages = ['it', 'de', 'en'];
        foreach ($languages as $language) {
            $copyRows = $rows;
            array_walk($copyRows, function (&$item) use ($base, $language) {
                $item['label'] = $item[$language] ?? $base[$item['value']];
                unset($item['it']);
                unset($item['de']);
                unset($item['en']);
            });
            usort($copyRows, function ($item1, $item2) {
                return $item1['label'] <=> $item2['label'];
            });
            $categories[$language] = $copyRows;
        }

        return $categories;
    }
}