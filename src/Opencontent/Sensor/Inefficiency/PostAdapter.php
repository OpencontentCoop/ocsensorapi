<?php

namespace Opencontent\Sensor\Inefficiency;

use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\Sensor\Api\Exception\InvalidInputException;
use Opencontent\Sensor\Api\Repository;
use eZUser;
use eZContentClass;
use OCCodiceFiscaleType;
use eZDB;
use Opencontent\Sensor\OpenApi\BuildSchemaPropertyTrait;
use Opencontent\Sensor\OpenApi\ReferenceSchema;

class PostAdapter
{
    use BuildSchemaPropertyTrait;
    
    private $repository;

    private $payload;

    private $tenants;

    private $serviceSlug;

    private $severityMap;

    private function __construct(Repository $repository, array $payload)
    {
        $this->repository = $repository;
        $this->payload = $payload;
        $this->tenants = (array)$this->repository->getSensorSettings()->get('Inefficiency')->tenants;
        $this->serviceSlug = $this->repository->getSensorSettings()->get('Inefficiency')->service_slug;
        $this->severityMap = $this->repository->getSensorSettings()->get('Inefficiency')->severity_map;
    }

    public static function instance(Repository $repository, array $payload): PostAdapter
    {
        return new PostAdapter($repository, $payload);
    }

    public function isValidPayload(): bool
    {
        return
            isset($this->payload['service'])
            && $this->payload['service'] === $this->serviceSlug
            && isset($this->payload['tenant'])
            && (
                in_array($this->payload['tenant'], $this->tenants)
                || in_array('*', $this->tenants)
            );
    }

    private function adaptType($severity)
    {
        return $this->severityMap[$severity] ?? 'segnalazione';
    }

    private function adaptCategory($type)
    {
        if (is_numeric($type['value'])) {
            return $type['value'];
        }
        return [];
    }

    public function adaptPayload()
    {
        $data = $this->payload['data'] ?? [];
        $address = $data['address'] ?? [];
        $adapted = [];
        $adapted['subject'] = $data['subject'] ?? null;
        $adapted['description'] = $data['details'] ?? null;
        $lat = $address['lat'] ?? $address['latitude'] ?? null;
        $lon = $address['lon'] ?? $address['longitude'] ?? null;
        if ($lat && $lon) {
            $adapted['address'] = [
                'address' => $address['display_name'] ?? $address['label'] ?? null,
                'latitude' => $lat,
                'longitude' => $lon,
            ];
        }
        $adapted['category'] = $this->adaptCategory($data['type'] ?? null);
        $adapted['type'] = $this->adaptType($data['severity'] ?? null);
        $adapted['is_private'] = true;
        $links = $this->payload['links'];
        $firstLink = array_shift($links);
        $findPingbackUrl = explode($this->payload['id'], $firstLink['url'] ?? '');
        $pingbackUrl = $findPingbackUrl[0] ? $findPingbackUrl[0] . $this->payload['id'] : null;
        $adapted['author'] = $this->getAuthorId(
            $this->payload['user'],
            $data['applicant'],
            $this->payload['authentication']
        );
        $adapted['channel'] = 'Sito web';
        $adapted['uuid'] = $this->payload['id'];
        $adapted['meta'] = [
            'pingback_url' => $pingbackUrl,
            'application' => $this->payload,
        ];
        $adapted['images'] = $this->adaptBinaries($this->payload['data']['images']);
        $adapted['files'] = $this->adaptBinaries($this->payload['data']['docs']);

        return $adapted;
    }

    private function adaptBinaries($payloads)
    {
        $files = [];
        foreach ($payloads as $payload) {
            $file = $this->adaptBinary($payload);
            if ($file) {
                $files[] = $file;
            }
        }
        return $files;
    }

    private function adaptBinary($payload)
    {
        $data = $this->repository->getInefficiencyClient()->downloadBinary($payload['url']);
        if (!$data) {
            return false;
        }
        return [
            'file' => base64_encode($data),
            'filename' => $payload['originalName'],
        ];
    }

    private function getAuthorId($userId, $applicant, $authentication)
    {
        if ($this->repository->getCurrentUser()->behalfOfMode !== true) {
            return null;
        }

        $user = eZUser::fetchByName($userId);
        if ($user instanceof eZUser) {
            return $user->id();
        }

        $isAnonymousUser = $authentication['authentication_method'] == 'anonymous';
        $fiscalCode = $applicant['fiscal_code']['fiscal_code'] ?? null;
        $email = $applicant['email_address'] ?? null;
        $phone = $applicant['phone_number'] ?? null;
        $firstName = $applicant['completename']['name'] ?? null;
        $lastName = $applicant['completename']['surname'] ?? null;
        $newUserPayload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'fiscal_code' => $fiscalCode,
            'phone' => $phone,
        ];

        if ($isAnonymousUser) {
            $parts = explode('@', $email);
            $email = $parts[0] . '+' . $userId . '@' . $parts[1]; //avoid using not trusted mail
            
            $user = eZUser::fetchByEmail($email);
            if ($user instanceof eZUser) {
                return $user->id();
            }

            $fiscalCode = null;
            $newUserPayload = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'fiscal_code' => '',
                'phone' => $phone,
            ];
        }

        if ($email) {
            $user = eZUser::fetchByEmail($email);
            if ($user instanceof eZUser) {
                return $user->id();
            }
        }

        if ($fiscalCode) {
            try {
                $userClass = eZContentClass::fetchByIdentifier('user');
                if ($userClass instanceof eZContentClass) {
                    foreach ($userClass->dataMap() as $attribute) {
                        if ($attribute->attribute('data_type_string') == OCCodiceFiscaleType::DATA_TYPE_STRING) {
                            $userObject = $this->fetchObjectByFiscalCode($attribute, $fiscalCode);
                            if ($userObject instanceof eZContentObject) {
                                $user = eZUser::fetch($userObject->attribute('id'));
                                if ($user instanceof eZUser) {
                                    return $user->id();
                                }
                            }
                        }
                    }
                }
            } catch (InvalidInputException $e) {
                $this->repository->getLogger()->warning($e->getMessage(), ['applicant' => $applicant]);
                $newUserPayload['fiscal_code'] = null;
            }
        }
        $newUser = $this->repository->getUserService()->createUser($newUserPayload, true);
        return $newUser->id;
    }

    private function fetchObjectByFiscalCode($contentClassAttribute, $fiscalCode)
    {
        $dataType = $contentClassAttribute->dataType();
        if ($dataType instanceof \OCCodiceFiscaleType) {
            $fakeObjectAttribute = new \eZContentObjectAttribute([
                'contentobject_id' => 0,
                'contentclassattribute_id' => $contentClassAttribute->attribute('id'),
            ]);
            if ($dataType->validateStringHTTPInput(
                    $fiscalCode,
                    $fakeObjectAttribute,
                    $contentClassAttribute
                ) === \eZInputValidator::STATE_INVALID) {
                throw new InvalidInputException($fakeObjectAttribute->validationError());
            }
        }

        $contentClassAttributeID = (int)$contentClassAttribute->attribute('id');

        $query = "SELECT co.id
				FROM ezcontentobject co, ezcontentobject_attribute coa
				WHERE co.id = coa.contentobject_id
				AND co.current_version = coa.version								
				AND coa.contentclassattribute_id = $contentClassAttributeID
				AND UPPER(coa.data_text) = '" . eZDB::instance()->escapeString(strtoupper($fiscalCode)) . "'";

        $result = eZDB::instance()->arrayQuery($query);
        if (isset($result[0]['id'])) {
            return eZContentObject::fetch((int)$result[0]['id']);
        }

        return false;
    }

    public static function buildApplicationSchema(): OA\Schema
    {
        return new OA\Schema([
            'title' => 'InefficiencyApplication',
            'type' => 'object',
            'properties' => [
                'id' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                'user' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                'data' => self::buildSchemaProperty([
                    'type' => 'object',
                    'properties' => [
                        'subject' => self::buildSchemaProperty(['type' => 'string']),
                        'details' => self::buildSchemaProperty(['type' => 'string']),
                        'applicant' => self::buildSchemaProperty([
                            'type' => 'object',
                            'properties' => [
                                'email_address' => self::buildSchemaProperty(['type' => 'string']),
                                'email_address' => self::buildSchemaProperty(['type' => 'string']),
                                'completename' => self::buildSchemaProperty([
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => self::buildSchemaProperty(['type' => 'string']),
                                        'surname' => self::buildSchemaProperty(['type' => 'string']),
                                    ],
                                ]),
                                'fiscal_code' => self::buildSchemaProperty([
                                    'type' => 'object',
                                    'properties' => [
                                        'fiscal_code' => self::buildSchemaProperty(['type' => 'string']),
                                    ],
                                ]),
                            ],
                        ]),
                        'address' => self::buildSchemaProperty([
                            'type' => 'object',
                            'properties' => [
                                'address' => self::buildSchemaProperty([
                                    'type' => 'object',
                                    'properties' => [
                                        'display_name' => self::buildSchemaProperty(['type' => 'string']),
                                        'label' => self::buildSchemaProperty(['type' => 'string']),
                                        'lat' => self::buildSchemaProperty(['type' => 'string']),
                                        'lon' => self::buildSchemaProperty(['type' => 'string']),
                                    ],
                                ]),
                            ],
                        ]),
                        'type' => self::buildSchemaProperty([
                            'type' => 'object',
                            'properties' => [
                                'label' => self::buildSchemaProperty(['type' => 'string']),
                                'value' => self::buildSchemaProperty(['type' => 'string']),
                            ],
                        ]),
                        'severity' => self::buildSchemaProperty(['type' => 'string', 'format' => 'number']),
                        'images' => self::buildSchemaProperty([
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                                    'originalName' => self::buildSchemaProperty(['type' => 'string']),
                                    'url' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uri']),
                                ],
                            ],
                        ]),
                        'docs' => self::buildSchemaProperty([
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uuid']),
                                    'originalName' => self::buildSchemaProperty(['type' => 'string']),
                                    'url' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uri']),
                                ],
                            ],
                        ]),
                    ],
                ]),
                'authentication' => self::buildSchemaProperty([
                    'type' => 'object',
                    'properties' => [
                        'authentication_method' => self::buildSchemaProperty(['type' => 'string']),
                    ],
                ]),
                'links' => self::buildSchemaProperty([
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => self::buildSchemaProperty(['type' => 'string']),
                            'description' => self::buildSchemaProperty(['type' => 'string']),
                            'url' => self::buildSchemaProperty(['type' => 'string', 'format' => 'uri']),
                        ],
                    ],
                ]),
            ],
        ]);
    }
}