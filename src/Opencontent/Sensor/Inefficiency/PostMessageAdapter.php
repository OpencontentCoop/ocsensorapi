<?php

namespace Opencontent\Sensor\Inefficiency;

use Opencontent\Sensor\Api\Repository;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\Sensor\OpenApi\BuildSchemaPropertyTrait;
use Opencontent\Sensor\OpenApi\ReferenceSchema;

class PostMessageAdapter
{
    use BuildSchemaPropertyTrait;
    
    private $repository;

    private $payload;

    private function __construct(Repository $repository, array $payload)
    {
        $this->repository = $repository;
        $this->payload = $payload;
    }

    public static function instance(Repository $repository, array $payload): PostMessageAdapter
    {
        return new PostMessageAdapter($repository, $payload);
    }

    /**
     * @throw \RuntimeException
     * @return bool
     */
    public function isValidPayload(): bool
    {
        if (isset($this->payload['external_id']) && !empty($this->payload['external_id'])){
            throw new \RuntimeException('Avoid republish already pushed message');
        }
        return
            isset($this->payload['related_entity_type'])
            && $this->payload['related_entity_type'] === 'application'
            && isset($this->payload['transmission_type'])
            && $this->payload['transmission_type'] === 'inbound'
            && isset($this->payload['visibility'])
            && $this->payload['visibility'] === 'applicant'
            && empty($this->payload['external_id']);
    }

    public static function buildMessageSchema(): OA\Schema
    {
        return new OA\Schema([
            'title' => 'InefficiencyApplicationMessage',
            'type' => 'object',
            'properties' => [
                'id' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                'application' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                'message' => self::buildSchemaProperty(['type' => 'string']),
                'attachments' => self::buildSchemaProperty([
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                            'original_name' => self::buildSchemaProperty(['type' => 'string']),
                            'url' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uri']),
                        ],
                    ],
                ]),
            ],
        ]);
    }
}