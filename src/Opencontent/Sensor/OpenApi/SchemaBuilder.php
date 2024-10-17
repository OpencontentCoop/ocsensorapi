<?php

namespace Opencontent\Sensor\OpenApi;

use erasys\OpenApi as OpenApiBase;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\Inefficiency\PostAdapter;
use Opencontent\Sensor\Inefficiency\PostMessageAdapter;
use Opencontent\Sensor\Inefficiency\CategoryAdapter;
use Opencontent\Sensor\Legacy\SearchService;
use Opencontent\Sensor\OpenApi;

class SchemaBuilder
{
    use BuildSchemaPropertyTrait;

    private $apiSettings;

    private $siteIni;

    /**
     * @var \eZContentClass
     */
    private $postClass;

    /**
     * @var \eZContentClassAttribute[]
     */
    private $postClassDataMap;

    private static $tags = [
        'auth' => "Authentication",
        'posts' => "Posts",
        'post-operators' => "Operators of post",
        'post-statuses' => "Statuses of post",
        'users' => "Users",
        'user-groups' => "Groups of users",
        'operators' => "Operators",
        'groups' => "Groups of operators",
        'categories' => "Categories",
        'types' => "Types",
        'areas' => "Areas",
        'stat' => "Statistics",
        'faq' => "FAQs",
    ];
    
    private $isJwtEnabled = false;

    private $isInefficiencyAdapterEnabled = false;

    private static $languageList;

    public function __construct(OpenApi $apiSettings)
    {
        $this->apiSettings = $apiSettings;
        $this->siteIni = \eZINI::instance();
        $this->postClass = $this->apiSettings->getRepository()->getPostContentClass();
        $this->postClassDataMap = $this->postClass->dataMap();
        if (class_exists('\SensorJwtManager')) {
            $this->isJwtEnabled = \SensorJwtManager::instance()->isJwtAuthEnabled();
        }
        $this->isInefficiencyAdapterEnabled = $this->apiSettings->getRepository()->getSensorSettings()
            ->get('Inefficiency')->is_enabled;
    }

    /**
     * return OA\Document
     */
    public function build()
    {
        self::$tags['inefficiencies'] = "Adapters";
        $inefficiencyPaths = $this->buildInefficiencyPaths();
        $loadInefficiencyPaths = [];
        if ($this->isInefficiencyAdapterEnabled){
            $loadInefficiencyPaths = $inefficiencyPaths;
        } else {
            $loadInefficiencyPaths['/inefficiency/categories'] = $inefficiencyPaths['/inefficiency/categories'];
        }

        $security = [
            ['basicAuth' => []],
        ];
        if ($this->isJwtEnabled) {
            $security[] = ['bearerAuth' => []];
        }
        $document = new OA\Document(
            $this->buildInfo(),
            array_merge(
                $this->buildAuthPaths(),
                $this->buildPostPaths(),
                $this->buildUserPaths(),
                $this->buildUserGroupPaths(),
                $this->buildOperatorPaths(),
                $this->buildGroupPaths(),
                $this->buildCategoryPaths(),
                $this->buildTypePaths(),
                $this->buildAreaPaths(),
                $this->buildStatisticPaths(),
                $this->buildFaqPaths(),
                $loadInefficiencyPaths
            ),
            '3.0.1',
            [
                'servers' => $this->buildServers(),
                'tags' => $this->buildTags(),
                'components' => $this->buildComponents(),
                'security' => $security
            ]
        );

        return $document;
    }

    /**
     * @see https://opensource.zalando.com/restful-api-guidelines/#218
     * @return OA\Info
     */
    private function buildInfo()
    {
        $contact = new OA\Contact();
        $contact->email = $this->siteIni->variable('MailSettings', 'AdminEmail');

        return new InfoWithAdditionalProperties(
            $this->siteIni->variable('SiteSettings', 'SiteName') . ' OpenSegnalazioni Api',
            //@see https://opensource.zalando.com/restful-api-guidelines/#218
            '1.0.0',
            'Web service to initialize and manage sensor posts with OpenSegnalazioni',
            [
                'termsOfService' => $this->apiSettings->siteUrl . '/terms',
                'contact' => $contact,
                'license' => new OA\License("GNU General Public License, version 2", "https://www.gnu.org/licenses/old-licenses/gpl-2.0.html"),

                //@todo @see https://opensource.zalando.com/restful-api-guidelines/#215
                'xApiId' => new OpenApiBase\ExtensionProperty('api-id', 'abb5bf40-077d-42f5-b0fe-079538e6d650'),

                //@see https://opensource.zalando.com/restful-api-guidelines/#219
                'xAudience' => new OpenApiBase\ExtensionProperty('audience', 'external-public'),
            ]
        );
    }

    private function buildServers()
    {
        return [
            new OA\Server($this->apiSettings->endpointUrl, 'Production server'),
        ];
    }

    private function buildTags()
    {
        $tags = [
            new OA\Tag(self::$tags['posts']),
            new OA\Tag(self::$tags['post-operators']),
            new OA\Tag(self::$tags['post-statuses']),
            new OA\Tag(self::$tags['users']),
            new OA\Tag(self::$tags['user-groups']),
            new OA\Tag(self::$tags['operators']),
            new OA\Tag(self::$tags['groups']),
            new OA\Tag(self::$tags['categories']),
            new OA\Tag(self::$tags['types']),
            new OA\Tag(self::$tags['areas']),
            new OA\Tag(self::$tags['stat']),
            new OA\Tag(self::$tags['faq']),
        ];
        if ($this->isJwtEnabled) {
            array_unshift($tags, new OA\Tag(self::$tags['auth']));
        }

        return $tags;
    }

    private function buildAuthPaths()
    {
        $path = [];
        if ($this->isJwtEnabled) {
            $path['/auth'] = new OA\PathItem([
                'post' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Schema([
                                    'title' => 'Token',
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'JWT Token'])
                                    ]
                                ])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '401' => new OA\Response('Unauthorized'),
                    ],
                    'auth',
                    'Retrieve authentication token',
                    [
                        'summary' => 'Retrieve authentication token',
                        'tags' => [self::$tags['auth']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Credentials'),
                        'security' => []
                    ]
                )
            ]);
        }

        return $path;
    }

    private function buildPostPaths()
    {
        return [
            '/posts' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PostCollection')
                            ]),
                            'application/vnd.geo+json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/FeatureCollection')
                            ]),
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadPosts',
                    'Get all posts',
                    [
                        'description' => 'Returns a list of post',
                        'tags' => [self::$tags['posts']],
                        'parameters' => array_merge(
                            $this->buildSearchParameters(['q', 'limit', 'offset', 'cursor', 'authorFiscalCode', 'categories', 'areas', 'status', 'type', 'channel', 'published', 'modified']),
                            $this->buildEmbedParameters(),
                            $this->buildSortParameters()
                        ),
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createPost',
                    'Add a new post',
                    [
                        'summary' => 'Post or application',
                        'tags' => [self::$tags['posts']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/NewPost')
                    ]
                ),
            ]),
            '/posts/{postId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ])],
                            null,
                            ['links' => [
                                'approvers' => $this->buildLink(['operationId' => 'getApproversByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'owners' => $this->buildLink(['operationId' => 'getOwnersByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'observers' => $this->buildLink(['operationId' => 'getObserversByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'comments' => $this->buildLink(['operationId' => 'getCommentsByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'privateMessages' => $this->buildLink(['operationId' => 'getPrivateMessagesByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'responses' => $this->buildLink(['operationId' => 'getResponsesByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'timeline' => $this->buildLink(['operationId' => 'getTimelineByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'areas' => $this->buildLink(['operationId' => 'getAreasByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'categories' => $this->buildLink(['operationId' => 'getCategoryByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'status' => $this->buildLink(['operationId' => 'getStatusByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'workflowStatus' => $this->buildLink(['operationId' => 'getWorkflowStatusByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'privacyStatus' => $this->buildLink(['operationId' => 'getPrivacyStatusByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'moderationStatus' => $this->buildLink(['operationId' => 'getModerationStatusByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                            ]]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPostById',
                    'Get single post',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => array_merge(
                            $this->buildInPathPostParameters(),
                            $this->buildEmbedParameters()
                        ),
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ])],
                            null,
                            ['links' => [
                                'approvers' => $this->buildLink(['operationId' => 'getApproversByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'owners' => $this->buildLink(['operationId' => 'getOwnersByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'observers' => $this->buildLink(['operationId' => 'getObserversByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'comments' => $this->buildLink(['operationId' => 'getCommentsByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'privateMessages' => $this->buildLink(['operationId' => 'getPrivateMessagesByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'responses' => $this->buildLink(['operationId' => 'getResponsesByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                                'timeline' => $this->buildLink(['operationId' => 'getTimelineByPostId', 'parameters' => ['postId' => '$request.path.postId']]),
                            ]]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updatePostById',
                    'Update single post',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\Reference('#/components/requestBodies/UpdatePost')
                    ]
                ),
            ]),
            '/posts/{postId}/approvers' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getApproversByPostId',
                    'Get post approvers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters()
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setApproversToPostId',
                    'Set post approvers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['participant_ids' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ])], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/owners' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getOwnersByPostId',
                    'Get post owners',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters()
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setOwnersToPostId',
                    'Set post owners',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['participant_ids' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ])], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/observers' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getObserversByPostId',
                    'Get post observers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters()
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setObserversToPostId',
                    'Set post observers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['participant_ids' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ])], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/participants' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getParticipantsByPostId',
                    'Get all participants',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters()
                    ]
                ),
            ]),
            '/posts/{postId}/participants/{participantId}/users' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/UserCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPostParticipantUsersByParticipantId',
                    'Get user in participant',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => $this->buildInPathPostParameters()
                    ]
                ),
            ]),
            '/posts/{postId}/comments' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PublicConversation')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCommentsByPostId',
                    'Get post comments',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Comment')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid format provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addCommentsToPostId',
                    'Add post comment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text'])]])
                        ])], 'Comment text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/comments/{commentId}' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Comment')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'editCommentsInPostId',
                    'Edit post comment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => array_merge($this->buildInPathPostParameters(), [
                            new OA\Parameter('commentId', OA\Parameter::IN_PATH, 'ID of comment to edit', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text'])]])
                        ])], 'Comment text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/privateMessages' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PrivateConversation')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPrivateMessagesByPostId',
                    'Get post private messages',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PrivateMessage')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addPrivateMessageToPostId',
                    'Add post private message',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                                'text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text']),
                                'receivers' => $this->buildSchemaProperty(['type' => 'array', 'items' => $this->buildSchemaProperty(['type' => 'integer'])]),
                            ]])
                        ])], 'Message text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/privateMessages/{privateMessageId}' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PrivateMessage')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'editPrivateMessageInPostId',
                    'Edit post private message',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => array_merge($this->buildInPathPostParameters(), [
                            new OA\Parameter('privateMessageId', OA\Parameter::IN_PATH, 'ID of private message to edit', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text'])]])
                        ])], 'Message text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/responses' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/ResponseCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getResponsesByPostId',
                    'Get post responses',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Response')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addResponsesToPostId',
                    'Add post response',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text'])]])
                        ])], 'Response text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/responses/{responseId}' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Response')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'editResponsesInPostId',
                    'Edit post response',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => array_merge($this->buildInPathPostParameters(), [
                            new OA\Parameter('responseId', OA\Parameter::IN_PATH, 'ID of response to edit', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text'])]])
                        ])], 'Response text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/attachments' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/AttachmentCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getAttachmentsByPostId',
                    'Get post attachments',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Attachment')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addAttachmentsToPostId',
                    'Add post attachment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['files' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Attachment']])]])
                        ])], 'Attachments', true)
                    ]
                ),
            ]),
            '/posts/{postId}/attachments/{filename}' => new OA\PathItem([
                'delete' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response'),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'deleteAttachmentsInPostId',
                    'Delete post attachment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => array_merge($this->buildInPathPostParameters(), [
                            new OA\Parameter('filename', OA\Parameter::IN_PATH, 'Filename of attachment to remove', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string']),
                                'required' => true,
                            ]),
                        ])
                    ]
                ),
            ]),
            '/posts/{postId}/timeline' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Timeline')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getTimelineByPostId',
                    'Get post timeline',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
            ]),
            '/posts/{postId}/areas' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/AreaCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getAreasByPostId',
                    'Get post areas',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/AreaCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setAreasToPostId',
                    'Set post areas',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['area_id' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ])], 'Area id list', true)
                    ]
                ),
            ]),
            '/posts/{postId}/categories' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/CategoryCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCategoriesByPostId',
                    'Get post categories',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/CategoryCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setCategoriesToPostId',
                    'Set post categories',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['category_id' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ])], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/status' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getStatusByPostId',
                    'Get post status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
            ]),
            '/posts/{postId}/workflowStatus' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getWorkflowStatusByPostId',
                    'Get post workflow status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setWorkflowStatusByPostId',
                    'Set post workflow status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string', 'enum' => ['read', 'fixed', 'closed', 'reopened']])]])
                        ])], 'Workflow status identifier', true)
                    ]
                ),
            ]),
            '/posts/{postId}/privacyStatus' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPrivacyStatusByPostId',
                    'Get post privacy status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setPrivacyStatusByPostId',
                    'Set post privacy status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string', 'enum' => ['public', 'private']])]])
                        ])], 'Privacy status identifier', true)
                    ]
                ),
            ]),
            '/posts/{postId}/moderationStatus' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getModerationStatusByPostId',
                    'Get post moderation status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setModerationStatusByPostId',
                    'Set post moderation status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string', 'enum' => ['waiting', 'accepted', 'refused']])]])
                        ])], 'Moderation status identifier', true)
                    ]
                ),
            ]),
            '/posts/{postId}/expiry' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['expiry_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Expiration date'])]])
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setExpiryByPostId',
                    'Set post expiration date',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildInPathPostParameters(),
                        'requestBody' => new OA\RequestBody(['application/json' => new OA\MediaType([
                            'schema' => $this->buildSchemaProperty(['type' => 'integer', 'minimum' => 1])
                        ])], 'Expiration days since post creation date', true)
                    ]
                ),
            ]),
        ];
    }

    private function buildUserPaths()
    {
        return [
            '/users' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/UserCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadUsers',
                    'Get all users',
                    [
                        'description' => 'Returns a list of user',
                        'tags' => [self::$tags['users']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createUser',
                    'Add a new user',
                    [
                        'summary' => 'Returns a list of user',
                        'tags' => [self::$tags['users']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/User')
                    ]
                ),
            ]),
            '/users/current' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCurrentUser',
                    'Get the current authenticated user',
                    [
                        'tags' => [self::$tags['users']],
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateCurrentUser',
                    'Update the current authenticated user',
                    [
                        'tags' => [self::$tags['users']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/User')
                    ]
                ),
                'patch' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'patchCurrentUser',
                    'Patch the current authenticated user',
                    [
                        'tags' => [self::$tags['users']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/PatchUser')
                    ]
                ),
            ]),
            '/users/current/posts' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PostCollection')
                            ]),
                            'application/vnd.geo+json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/FeatureCollection')
                            ]),
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCurrentUserPosts',
                    'Get all posts that the current user is the author of',
                    [
                        'tags' => [self::$tags['users']],
                        'parameters' => array_merge(
                            $this->buildSearchParameters(['q', 'limit', 'offset', 'cursor']),
                            $this->buildEmbedParameters()
                        ),
                    ]
                ),
            ]),
            '/users/{userId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getUserById',
                    'Get single user',
                    [
                        'tags' => [self::$tags['users']],
                        'parameters' => [
                            new OA\Parameter('userId', OA\Parameter::IN_PATH, 'ID of user', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateUserById',
                    'Update single user',
                    [
                        'tags' => [self::$tags['users']],
                        'parameters' => [
                            new OA\Parameter('userId', OA\Parameter::IN_PATH, 'ID of user', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/User')
                    ]
                ),
                'patch' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'patchUserById',
                    'Patch single user',
                    [
                        'tags' => [self::$tags['users']],
                        'parameters' => [
                            new OA\Parameter('userId', OA\Parameter::IN_PATH, 'ID of user', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/PatchUser')
                    ]
                ),
            ]),
            '/users/{userId}/posts' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/PostCollection')
                            ]),
                            'application/vnd.geo+json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/FeatureCollection')
                            ]),
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getUserByIdPosts',
                    'Get all posts that the selected user is the author of',
                    [
                        'tags' => [self::$tags['users']],
                        'parameters' => array_merge(
                            [new OA\Parameter('userId', OA\Parameter::IN_PATH, 'ID of user', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ])],
                            $this->buildSearchParameters(['q', 'limit', 'offset', 'cursor']),
                            $this->buildEmbedParameters()
                        ),
                    ]
                ),
            ]),
        ];
    }

    private function buildUserGroupPaths()
    {
        return [
            '/user-groups' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/UserGroupCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadUserGroups',
                    'Get all user groups',
                    [
                        'description' => 'Returns a list of user group',
                        'tags' => [self::$tags['user-groups']],
                        'parameters' => $this->buildSearchParameters(['limit', 'offset'])
                    ]
                ),
//                'post' => new OA\Operation(
//                    [
//                        '201' => new OA\Response('Successful response', [
//                            'application/json' => new OA\MediaType([
//                                'schema' => new OA\Reference('#/components/schemas/UserGroup')
//                            ])
//                        ]),
//                        '400' => new OA\Response('Invalid input provided'),
//                        '403' => new OA\Response('Forbidden'),
//                        '405' => new OA\Response('Invalid input'),
//                    ],
//                    'createUserGroup',
//                    'Add a new user group',
//                    [
//                        'summary' => 'Returns a list of user group',
//                        'tags' => [self::$tags['user-groups']],
//                        'requestBody' => new OA\Reference('#/components/requestBodies/UserGroup')
//                    ]
//                ),
            ]),
            '/user-groups/{userGroupId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/UserGroup')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getUserGroupById',
                    'Get single user group',
                    [
                        'tags' => [self::$tags['user-groups']],
                        'parameters' => [
                            new OA\Parameter('userGroupId', OA\Parameter::IN_PATH, 'ID of user group', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
//                'put' => new OA\Operation(
//                    [
//                        '200' => new OA\Response('Successful response',
//                            ['application/json' => new OA\MediaType([
//                                'schema' => new OA\Reference('#/components/schemas/UserGroup')
//                            ])], null),
//                        '400' => new OA\Response('Invalid input provided'),
//                        '403' => new OA\Response('Forbidden'),
//                        '404' => new OA\Response('Not found'),
//                    ],
//                    'updateUserGroupById',
//                    'Update single user group',
//                    [
//                        'tags' => [self::$tags['user-groups']],
//                        'parameters' => [
//                            new OA\Parameter('userGroupId', OA\Parameter::IN_PATH, 'ID of user group', [
//                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
//                                'required' => true,
//                            ]),
//                        ],
//                        'requestBody' => new OA\Reference('#/components/requestBodies/UserGroup')
//                    ]
//                ),
            ]),
//            '/user-groups/{userGroupId}/users' => new OA\PathItem([
//                'get' => new OA\Operation(
//                    [
//                        '200' => new OA\Response('Successful response',
//                            ['application/json' => new OA\MediaType([
//                                'schema' => new OA\Reference('#/components/schemas/UserCollection')
//                            ])], null),
//                        '400' => new OA\Response('Invalid input provided'),
//                        '404' => new OA\Response('Not found'),
//                    ],
//                    'getUserGroupUsersById',
//                    'Get user-group users',
//                    [
//                        'tags' => [self::$tags['user-groups']],
//                        'parameters' => [
//                            new OA\Parameter('userGroupId', OA\Parameter::IN_PATH, 'ID of user group', [
//                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
//                                'required' => true,
//                            ]),
//                            new OA\Parameter('limit', OA\Parameter::IN_QUERY, 'Limit to restrict the number of entries on a page', [
//                                'schema' => $this->buildSchemaProperty(['type' => 'integer', 'minimum' => 1, 'maximum' => SearchService::MAX_LIMIT, 'default' => SearchService::DEFAULT_LIMIT, 'nullable' => true]),
//                            ]),
//                            new OA\Parameter('cursor', OA\Parameter::IN_QUERY, 'Cursor pagination', [
//                                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => '*', 'nullable' => true]),
//                            ])
//                        ]
//                    ]
//                )
//            ]),
        ];
    }

    private function buildOperatorPaths()
    {
        return [
            '/operators' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/OperatorCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadOperators',
                    'Get all operators',
                    [
                        'description' => 'Returns a list of operator',
                        'tags' => [self::$tags['operators']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Operator')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createOperator',
                    'Add a new operator',
                    [
                        'summary' => 'Returns a list of operator',
                        'tags' => [self::$tags['operators']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Operator')
                    ]
                ),
            ]),
            '/operators/{operatorId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Operator')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getOperatorById',
                    'Get single operator',
                    [
                        'tags' => [self::$tags['operators']],
                        'parameters' => [
                            new OA\Parameter('operatorId', OA\Parameter::IN_PATH, 'ID of operator', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Operator')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateOperatorById',
                    'Update single operator',
                    [
                        'tags' => [self::$tags['operators']],
                        'parameters' => [
                            new OA\Parameter('operatorId', OA\Parameter::IN_PATH, 'ID of operator', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Operator')
                    ]
                ),
            ]),
        ];
    }

    private function buildGroupPaths()
    {
        return [
            '/groups' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/GroupCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadGroups',
                    'Get all groups',
                    [
                        'description' => 'Returns a list of group',
                        'tags' => [self::$tags['groups']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createGroup',
                    'Add a new group',
                    [
                        'summary' => 'Returns a list of group',
                        'tags' => [self::$tags['groups']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Group')
                    ]
                ),
            ]),
            '/groups/{groupId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getGroupById',
                    'Get single group',
                    [
                        'tags' => [self::$tags['groups']],
                        'parameters' => [
                            new OA\Parameter('groupId', OA\Parameter::IN_PATH, 'ID of group', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateGroupById',
                    'Update single group',
                    [
                        'tags' => [self::$tags['groups']],
                        'parameters' => [
                            new OA\Parameter('groupId', OA\Parameter::IN_PATH, 'ID of group', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Group')
                    ]
                ),
            ]),
            '/groups/{groupId}/operators' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/OperatorCollection')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getGroupOperatorsById',
                    'Get single group operators',
                    [
                        'tags' => [self::$tags['groups']],
                        'parameters' => [
                            new OA\Parameter('groupId', OA\Parameter::IN_PATH, 'ID of group', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                            new OA\Parameter('limit', OA\Parameter::IN_QUERY, 'Limit to restrict the number of entries on a page', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer', 'minimum' => 1, 'maximum' => SearchService::MAX_LIMIT, 'default' => SearchService::DEFAULT_LIMIT, 'nullable' => true]),
                            ]),
                            new OA\Parameter('cursor', OA\Parameter::IN_QUERY, 'Cursor pagination', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => '*', 'nullable' => true]),
                            ])
                        ]
                    ]
                )
            ]),
        ];
    }

    private function buildCategoryPaths()
    {
        return [
            '/categories' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/CategoryCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadCategories',
                    'Get all categories',
                    [
                        'description' => 'Returns a list of category',
                        'tags' => [self::$tags['categories']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Category')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createCategory',
                    'Add a new category',
                    [
                        'summary' => 'Create a category',
                        'tags' => [self::$tags['categories']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Category')
                    ]
                ),
            ]),
            '/categories/{categoryId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Category')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCategoryById',
                    'Get single category',
                    [
                        'tags' => [self::$tags['categories']],
                        'parameters' => [
                            new OA\Parameter('categoryId', OA\Parameter::IN_PATH, 'ID of category', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Category')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateCategoryById',
                    'Update single category',
                    [
                        'tags' => [self::$tags['categories']],
                        'parameters' => [
                            new OA\Parameter('categoryId', OA\Parameter::IN_PATH, 'ID of category', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Category')
                    ]
                ),
            ]),
        ];
    }

    private function buildAreaPaths()
    {
        return [
            '/areas' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/AreaCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadAreas',
                    'Get all areas',
                    [
                        'description' => 'Returns a list of area',
                        'tags' => [self::$tags['areas']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Area')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createArea',
                    'Add a new area',
                    [
                        'summary' => 'Returns a list of area',
                        'tags' => [self::$tags['areas']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Area')
                    ]
                ),
            ]),
            '/areas/{areaId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Area')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getAreaById',
                    'Get single area',
                    [
                        'tags' => [self::$tags['areas']],
                        'parameters' => [
                            new OA\Parameter('areaId', OA\Parameter::IN_PATH, 'ID of area', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Area')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateAreaById',
                    'Update single area',
                    [
                        'tags' => [self::$tags['areas']],
                        'parameters' => [
                            new OA\Parameter('areaId', OA\Parameter::IN_PATH, 'ID of area', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Area')
                    ]
                ),
            ]),
        ];
    }

    private function buildTypePaths()
    {
        return [
            '/types' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/TypeCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadTypes',
                    'Get all post types',
                    [
                        'description' => 'Returns a list of type',
                        'tags' => [self::$tags['types']]
                    ]
                )
            ]),
            '/types/{typeIdentifier}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Type')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getTypeByIdentifier',
                    'Get single post type by identifier',
                    [
                        'tags' => [self::$tags['types']],
                        'parameters' => [
                            new OA\Parameter('typeIdentifier', OA\Parameter::IN_PATH, 'Identifier of type', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string']),
                                'required' => true,
                            ]),
                        ]
                    ]
                )
            ]),
        ];
    }

    private function buildStatisticPaths()
    {
        $factories = [];
        foreach ($this->apiSettings->getRepository()->getStatisticsService()->getStatisticFactories(true) as $factory) {
            $factories[] = $factory->getIdentifier();
        }

        return [
            '/stats' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/StatCollection')
                            ])
                        ])
                    ],
                    'loadStats',
                    'Get all stats',
                    [
                        'description' => 'Returns a list of stats',
                        'tags' => [self::$tags['stat']]
                    ]
                )
            ]),
            '/stats/{statIdentifier}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => [
                                    'oneOf' => [
                                        new OA\Reference('#/components/schemas/Stat'),
                                        new OA\Reference('#/components/schemas/StatusStat'),
                                        new OA\Reference('#/components/schemas/AvgStat'),
                                    ]
                                ]
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getStatByIdentifier',
                    'Get single stat data',
                    [
                        'tags' => [self::$tags['stat']],
                        'parameters' => [
                            new OA\Parameter('statIdentifier', OA\Parameter::IN_PATH, 'ID of stat', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string', 'enum' => $factories]),
                                'required' => true,
                            ]),
                            new OA\Parameter('interval', OA\Parameter::IN_QUERY, 'Time interval of stat data', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => StatisticFactory::DEFAULT_INTERVAL, 'enum' => ['monthly', 'quarterly', 'half-yearly', 'yearly']]),
                            ]),
                            new OA\Parameter('category', OA\Parameter::IN_QUERY, 'Category id', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                            ]),
                            new OA\Parameter('area', OA\Parameter::IN_QUERY, 'Area id', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                            ]),
                            new OA\Parameter('format', OA\Parameter::IN_QUERY, 'Stat data view format', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => 'default', 'enum' => StatisticFactory::getAvailableFormats()]),
                            ]),
                        ]
                    ]
                )
            ]),
            '/stats/{statIdentifier}/user/{authorFiscalCode}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Stat')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getUserStatByIdentifier',
                    'Get single stat data filtered by author fiscal code',
                    [
                        'tags' => [self::$tags['stat']],
                        'parameters' => [
                            new OA\Parameter('statIdentifier', OA\Parameter::IN_PATH, 'ID of stat', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string']),
                                'required' => true,
                            ]),
                            new OA\Parameter('authorFiscalCode', OA\Parameter::IN_PATH, 'Author fiscal code', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string']),
                                'required' => true,
                            ]),
                            new OA\Parameter('interval', OA\Parameter::IN_QUERY, 'Time interval of stat data', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => StatisticFactory::DEFAULT_INTERVAL, 'enum' => ['monthly', 'quarterly', 'half-yearly', 'yearly']]),
                            ]),
                            new OA\Parameter('category', OA\Parameter::IN_QUERY, 'Category id', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                            ]),
                            new OA\Parameter('area', OA\Parameter::IN_QUERY, 'Area id', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                            ]),
                        ]
                    ]
                )
            ]),
        ];
    }

    private function buildFaqPaths()
    {
        return [
            '/faq' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/FaqCollection')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadFaqs',
                    'Get all frequently asked questions',
                    [
                        'description' => 'Returns a list of frequently asked questions',
                        'tags' => [self::$tags['faq']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Faq')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createFaq',
                    'Add a new frequently asked question',
                    [
                        'summary' => 'Returns a list of faq',
                        'tags' => [self::$tags['faq']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Faq')
                    ]
                ),
            ]),
            '/faq/{faqId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Faq')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getFaqById',
                    'Get single frequently asked question',
                    [
                        'tags' => [self::$tags['faq']],
                        'parameters' => [
                            new OA\Parameter('faqId', OA\Parameter::IN_PATH, 'ID of faq', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Faq')
                            ])], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'updateFaqById',
                    'Update single frequently asked question',
                    [
                        'tags' => [self::$tags['faq']],
                        'parameters' => [
                            new OA\Parameter('faqId', OA\Parameter::IN_PATH, 'ID of faq', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Faq')
                    ]
                ),
            ]),
        ];
    }

    private function buildInefficiencyPaths()
    {
        return [
            '/inefficiency' => new OA\PathItem([
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                        '406' => new OA\Response('Not accetable'),
                    ],
                    'createInefficiency',
                    'Create a new post from inefficiency application',
                    [
                        'summary' => 'Create a new post from inefficiency application',
                        'tags' => [self::$tags['inefficiencies']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/InefficiencyApplication')
                    ]
                ),
            ]),
            '/inefficiency/message' => new OA\PathItem([
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/Comment')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                        '406' => new OA\Response('Not accetable'),
                    ],
                    'createInefficiencyMessage',
                    'Create a new post message from inefficiency application message',
                    [
                        'summary' => 'Create a new post message from inefficiency application message',
                        'tags' => [self::$tags['inefficiencies']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/InefficiencyApplicationMessage')
                    ]
                ),
            ]),
            '/inefficiency/categories' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => new OA\MediaType([
                                'schema' => new OA\Reference('#/components/schemas/InefficiencyCategories')
                            ])
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadInefficiencyCategories',
                    'Get inefficiency application categories',
                    [
                        'description' => 'Get inefficiency application categories',
                        'tags' => [self::$tags['inefficiencies']],
                    ]
                ),
            ]),
        ];
    }


    private function buildSearchParameters($keys = ['q', 'limit', 'cursor'])
    {
        $parameters = [];
        if (in_array('q', $keys)) {
            $parameters[] = new OA\Parameter('q', OA\Parameter::IN_QUERY, 'Query parameter', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true]),
            ]);
        }
        if (in_array('limit', $keys)) {
            $parameters[] = new OA\Parameter('limit', OA\Parameter::IN_QUERY, 'Limit to restrict the number of entries on a page', [
                'schema' => $this->buildSchemaProperty(['type' => 'integer', 'minimum' => 1, 'maximum' => SearchService::MAX_LIMIT, 'default' => SearchService::DEFAULT_LIMIT, 'nullable' => true]),
            ]);
        }
        if (in_array('offset', $keys)) {
            $parameters[] = new OA\Parameter('offset', OA\Parameter::IN_QUERY, 'Numeric offset of the first element provided on a page representing a collection request', [
                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
            ]);
        }
        if (in_array('cursor', $keys)) {
            $parameters[] = new OA\Parameter('cursor', OA\Parameter::IN_QUERY, 'Cursor pagination', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => '*', 'nullable' => true]),
            ]);
        }
        if (in_array('authorFiscalCode', $keys)) {
            $parameters[] = new OA\Parameter('authorFiscalCode', OA\Parameter::IN_QUERY, 'Filter by author fiscal code', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true]),
            ]);
        }
        if (in_array('categories', $keys)) {
            $parameters[] = new OA\Parameter('categories', OA\Parameter::IN_QUERY, 'Filter by category id list', [
                'schema' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'nullable' => true]),
                'style' =>  'form',
                'explode' =>  false,
            ]);
        }
        if (in_array('areas', $keys)) {
            $parameters[] = new OA\Parameter('areas', OA\Parameter::IN_QUERY, 'Filter by area id list', [
                'schema' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true]),
                'style' =>  'form',
                'explode' =>  false,
            ]);
        }
        if (in_array('status', $keys)) {
            $parameters[] = new OA\Parameter('status', OA\Parameter::IN_QUERY, 'Filter by status identifier', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'enum' => $this->getStatusEnum()]),
            ]);
        }
        if (in_array('type', $keys)) {
            $parameters[] = new OA\Parameter('type', OA\Parameter::IN_QUERY, 'Filter by type identifier', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'enum' => $this->getTypeEnum()]),
            ]);
        }
        if (in_array('channel', $keys)) {
            $parameters[] = new OA\Parameter('channel', OA\Parameter::IN_QUERY, 'Filter by channel', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'enum' => $this->getChannelEnum()]),
            ]);
        }
        if (in_array('published', $keys)) {
            $parameters[] = new OA\Parameter('publishedFrom', OA\Parameter::IN_QUERY, 'Filter from publication date (full-date notation as defined by RFC 3339, section 5.6)', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'format' => 'date-time', 'example' => '2017-12-05']),
            ]);
            $parameters[] = new OA\Parameter('publishedTo', OA\Parameter::IN_QUERY, 'Filter to publication date (full-date notation as defined by RFC 3339, section 5.6)', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'format' => 'date-time']),
            ]);
        }
        if (in_array('modified', $keys)) {
            $parameters[] = new OA\Parameter('modifiedFrom', OA\Parameter::IN_QUERY, 'Filter from modification date (full-date notation as defined by RFC 3339, section 5.6)', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'format' => 'date-time']),
            ]);
            $parameters[] = new OA\Parameter('modifiedTo', OA\Parameter::IN_QUERY, 'Filter to modification date (full-date notation as defined by RFC 3339, section 5.6)', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true, 'format' => 'date-time']),
            ]);
        }
        return $parameters;
    }

    private function buildInPathPostParameters()
    {
        return [
            new OA\Parameter('postId', OA\Parameter::IN_PATH, ' ID (integer) or GUID (string) of post', [
                'schema' => $this->buildSchemaProperty([
                    'oneOf' => [
                        $this->buildSchemaProperty(['type' => 'integer']),
                        $this->buildSchemaProperty(['type' => 'string']),
                    ]
                ]),
                'required' => true,
            ]),
        ];
    }

    private function buildEmbedParameters()
    {
        $parameters = [];
        $parameters[] = new OA\Parameter('embed', OA\Parameter::IN_QUERY, 'Allow to embed the nested resources. Allowed comma separated multiple value (e.g. embed=privateMessages,responses)', [
            'schema' => $this->buildSchemaProperty([
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => [
                    'comments',
                    'privateMessages',
                    'responses',
                    'attachments',
                    'timeline',
                    'areas',
                    'categories',
                    'approvers',
                    'owners',
                    'observers',
                ]],
                'nullable' => true,
            ]),
            'style' =>  'form',
            'explode' =>  false,
        ]);

        return $parameters;
    }

    private function buildSortParameters()
    {
        $parameters = [];
        $sortFields = array_keys(Controller::getSortFieldMap());
        $parameters[] = new OA\Parameter('sortField', OA\Parameter::IN_QUERY, 'Sort results by field', [
            'schema' => $this->buildSchemaProperty([
                'type' => 'string',
                'enum' => $sortFields,
                'nullable' => true,
            ]),
            'style' =>  'form',
            'explode' =>  false,
        ]);
        $parameters[] = new OA\Parameter('sortDirection', OA\Parameter::IN_QUERY, 'Sort direction', [
            'schema' => $this->buildSchemaProperty([
                'type' => 'string',
                'enum' => [
                    'asc',
                    'desc',
                ],
                'nullable' => true,
            ]),
            'style' =>  'form',
            'explode' =>  false,
        ]);

        return $parameters;
    }

    private function buildComponents()
    {
        $components = new OA\Components();

        //@todo @see https://opensource.zalando.com/restful-api-guidelines/#104
        $components->securitySchemes = [
            'basicAuth' => new OA\SecurityScheme('http', null, ['scheme' => 'basic']),
        ];
        if ($this->isJwtEnabled) {
            $components->securitySchemes['bearerAuth'] = new OA\SecurityScheme('http', null, ['scheme' => 'bearer', 'bearerFormat' => 'JWT']);
        }

        $components->schemas = [
            'PostCollection' => $this->buildSchema('PostCollection'),
            'FeatureCollection' => $this->buildSchema('FeatureCollection'),
            'Post' => $this->buildSchema('Post'),
            'NewPost' => $this->buildSchema('NewPost'),
            'UpdatePost' => $this->buildSchema('UpdatePost'),

            'Address' => $this->buildSchema('Address'),
            'Image' => $this->buildSchema('Image'),
            'File' => $this->buildSchema('File'),
            'Attachment' => $this->buildSchema('Attachment'),
            'AttachmentCollection' => $this->buildSchema('AttachmentCollection'),

            'ParticipantCollection' => $this->buildSchema('ParticipantCollection'),
            'Participant' => $this->buildSchema('Participant'),

            'PublicConversation' => $this->buildSchema('PublicConversation'),
            'Comment' => $this->buildSchema('Comment'),

            'ResponseCollection' => $this->buildSchema('ResponseCollection'),
            'Response' => $this->buildSchema('Response'),

            'PrivateConversation' => $this->buildSchema('PrivateConversation'),
            'PrivateMessage' => $this->buildSchema('PrivateMessage'),

            'Timeline' => $this->buildSchema('Timeline'),
            'TimelineItem' => $this->buildSchema('TimelineItem'),

            'AreaCollection' => $this->buildSchema('AreaCollection'),
            'Area' => $this->buildSchema('Area'),
            'NewArea' => $this->buildSchema('NewArea'),

            'CategoryCollection' => $this->buildSchema('CategoryCollection'),
            'Category' => $this->buildSchema('Category'),
            'NewCategory' => $this->buildSchema('NewCategory'),

            'UserCollection' => $this->buildSchema('UserCollection'),
            'User' => $this->buildSchema('User'),
            'NewUser' => $this->buildSchema('NewUser'),
            'PatchUser' => $this->buildSchema('PatchUser'),

            'UserGroupCollection' => $this->buildSchema('UserGroupCollection'),
            'UserGroup' => $this->buildSchema('UserGroup'),
            'NewUserGroup' => $this->buildSchema('NewUserGroup'),

            'OperatorCollection' => $this->buildSchema('OperatorCollection'),
            'Operator' => $this->buildSchema('Operator'),
            'NewOperator' => $this->buildSchema('NewOperator'),

            'GroupCollection' => $this->buildSchema('GroupCollection'),
            'Group' => $this->buildSchema('Group'),
            'NewGroup' => $this->buildSchema('NewGroup'),

            'StatCollection' => $this->buildSchema('StatCollection'),
            'Stat' => $this->buildSchema('Stat'),
            'StatusStat' => $this->buildSchema('StatusStat'),
            'AvgStat' => $this->buildSchema('AvgStat'),

            'FaqCollection' => $this->buildSchema('FaqCollection'),
            'Faq' => $this->buildSchema('Faq'),
            'NewFaq' => $this->buildSchema('NewFaq'),

            'TypeCollection' => $this->buildSchema('TypeCollection'),
            'Type' => $this->buildSchema('Type'),
        ];

        $components->requestBodies = [
            'Post' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewPost')
            ])], 'Post object that needs to be created', true),
            'NewPost' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewPost'),
            ])], 'New post struct', true),
            'UpdatePost' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/UpdatePost'),
            ])], 'Update post struct', true),

            'User' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewUser')
            ])], 'User object that needs to be added or updated', true),
            'PatchUser' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/PatchUser')
            ])], 'User object that needs to be patched', true),
            'Operator' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewOperator')
            ])], 'Operator object that needs to be added or updated', true),
            'Group' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewGroup')
            ])], 'Group object that needs to be added or updated', true),

            'Area' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewArea')
            ])], 'Area object that needs to be added or updated', true),
            'Category' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewCategory')
            ])], 'Category object that needs to be added or updated', true),

            'Credentials' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Schema([
                    'title' => 'Credentials',
                    'type' => 'object',
                    'properties' => [
                        'username' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Username']),
                        'password' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Password']),
                    ]
                ])
            ])], 'Authorization credentials', true),

            'Faq' => new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/NewFaq')
            ])], 'Faq object that needs to be added or updated', true),
        ];

        if ($this->isInefficiencyAdapterEnabled){
            $components->schemas['InefficiencyApplication'] = PostAdapter::buildApplicationSchema();
            $components->schemas['InefficiencyApplicationMessage'] = PostMessageAdapter::buildMessageSchema();
            $components->requestBodies['InefficiencyApplication'] = new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/InefficiencyApplication')
            ])], 'Inefficiency application', true);
            $components->requestBodies['InefficiencyApplicationMessage'] = new OA\RequestBody(['application/json' => new OA\MediaType([
                'schema' => new OA\Reference('#/components/schemas/InefficiencyApplicationMessage')
            ])], 'Inefficiency application message', true);
        }
        $components->schemas['InefficiencyCategories'] = CategoryAdapter::buildCategorySchema();
        ksort($components->schemas);
        ksort($components->requestBodies);

        return $components;
    }

    private function buildSchema($schemaName)
    {
        $schema = new OA\Schema();

        $typeEnum = $this->getTypeEnum();

        $channelEnum = $this->getChannelEnum();

        switch ($schemaName) {
            case 'Address':
                $schema->title = 'Address';
                $schema->type = 'object';
                $schema->properties = [
                    'address' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Address']),
                    'longitude' => $this->buildSchemaProperty(['type' => 'number', 'description' => 'Longitude']),
                    'latitude' => $this->buildSchemaProperty(['type' => 'number', 'description' => 'Latitude']),
                ];
                break;
            case 'Image':
                $schema->title = 'Image';
                $schema->type = 'object';
                $schema->properties = [
                    'filename' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'File name']),
                    'file' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'byte', 'description' => 'base64-encoded file contents']),
                ];
                break;
            case 'File':
                $schema->title = 'File';
                $schema->type = 'object';
                $schema->properties = [
                    'filename' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'File name']),
                    'file' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'byte', 'description' => 'base64-encoded file contents']),
                ];
                break;
            case 'Attachment':
                $schema->title = 'Attachment';
                $schema->type = 'object';
                $schema->properties = [
                    'filename' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'File name']),
                    'file' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'byte', 'description' => 'base64-encoded file contents']),
                ];
                break;
            case 'AttachmentCollection':
                $schema->title = 'AttachmentCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'url', 'description' => 'File'])]),
                ];
                break;

            case 'PostCollection':
                $schema->title = 'PostCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Post']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;

            case 'NewPost':
            case 'UpdatePost':
                $schema->title = $schemaName;
                $attributeList = $this->apiSettings->getRepository()->getPublicPostContentClassAttributes();

                foreach ($attributeList as $identifier => $attribute) {
                    if ($identifier == 'geo') {
                        $identifier = 'address';
                        $properties = [
                            'description' => $attribute->attribute('name'),
                            'nullable' => true,
                            'ref' => '#/components/schemas/Address'
                        ];
                    } elseif ($identifier == 'area') {
                        $properties = [
                            'type' => 'integer',
                            'description' => $attribute->attribute('name'),
                            'maximum' => 1,
                            'nullable' => true
                        ];
                    } elseif ($identifier == 'category') {
                        $properties = [
                            'type' => 'integer',
                            'description' => $attribute->attribute('name'),
                            'maximum' => 1,
                            'nullable' => true
                        ];
                    } elseif ($identifier == 'image') {
                        $properties = [
                            'description' => $attribute->attribute('name'),
                            'ref' => '#/components/schemas/Image'
                        ];
                    } elseif ($identifier == 'images') {
                        $properties = [
                            'type' => 'array',
                            'description' => $attribute->attribute('name'),
                            'items' => ['ref' => '#/components/schemas/Image']
                        ];
                    } elseif ($identifier == 'files') {
                        $properties = [
                            'type' => 'array',
                            'description' => $attribute->attribute('name'),
                            'items' => ['ref' => '#/components/schemas/File']
                        ];
                    } elseif ($identifier == 'type') {
                        $properties = [
                            'description' => $attribute->attribute('name'),
                            'type' => 'string',
                            'enum' => $typeEnum,
                            'default' => $typeEnum[0],
                        ];
                    } elseif ($identifier == 'privacy') {
                        $identifier = 'is_private';
                        $properties = [
                            'type' => 'boolean',
                            'description' => $attribute->attribute('name'),
                            'default' => false
                        ];
                    } else {
                        $properties = [
                            'type' => 'string',
                            'description' => $attribute->attribute('name'),
                        ];
                    }

                    $schema->properties[$identifier] = $this->buildSchemaProperty($properties);
                    if ($attribute->attribute('is_required')) {
                        $schema->required[] = $identifier;
                    }
                }

                $schema->properties['channel'] = $this->buildSchemaProperty([
                    'type' => 'string',
                    'enum' => $channelEnum
                ]);
                $schema->properties['author'] = $this->buildSchemaProperty([
                    'type' => 'integer',
                    'description' => 'Author id',
                    'maximum' => 1
                ]);
                $schema->properties['author_email'] = $this->buildSchemaProperty([
                    'type' => 'string',
                    'description' => 'Author email address (create a new user if mail address is not registered)',
                    'format' => 'email'
                ]);

                if ($schemaName == 'NewPost'){
                    $schema->properties['uuid'] = $this->buildSchemaProperty([
                        'type' => 'string',
                        'description' => 'Universal unique identifier',
                    ]);
                }

                break;
            case 'Post':
                $schema->title = 'Post';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Post ID', 'description' => 'Unique identifier']),
                    'uuid' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Universal unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last modification date']),
                    'expiry_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Expiration date', 'nullable' => true]),
                    'closed_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Closing date', 'nullable' => true]),
                    'subject' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Subject']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'address' => $this->buildSchemaProperty(['ref' => '#/components/schemas/Address']),
                    'type' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Type', 'enum' => $typeEnum]),
                    'status' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Status', 'enum' => $this->getStatusEnum()]),
                    'privacy_status' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Privacy status', 'enum' => ['public', 'private']]),
                    'moderation_status' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Moderation status', 'enum' => ['waiting', 'approved']]),
                    'author' => $this->buildSchemaProperty(['type' => 'integer', 'description' => 'Author']),
                    'reporter' => $this->buildSchemaProperty(['type' => 'integer', 'description' => 'Reporter']),
                    'image' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'url', 'description' => 'Image (first post image)', 'nullable' => true]),
                    'images' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url'], 'description' => 'Images']),
                    'files' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string', 'format' => 'url'], 'description' => 'Files']),
                    'is_comments_allowed' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Comments allowed']),
                    'address_meta_info' => $this->buildSchemaProperty(['type' => 'object', 'description' => 'Key/value meta informations about geo location']),
                    'channel' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Channel', 'enum' => $channelEnum]),
                ];
                break;

            case 'PublicConversation':
                $schema->title = 'PublicConversation';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Comment']]),
                ];
                break;
            case 'Comment':
                $schema->title = 'Comment';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'description' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text']),
                ];
                break;

            case 'PrivateConversation':
                $schema->title = 'PrivateConversation';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/PrivateMessage']]),
                ];
                break;
            case 'PrivateMessage':
                $schema->title = 'PrivateMessage';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'description' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text']),
                    'receivers' => $this->buildSchemaProperty(['type' => 'array', 'description' => 'Receivers', 'items' => ['type' => 'integer']]),
                ];
                break;

            case 'Response':
                $schema->title = 'Response';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'description' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text']),
                ];
                break;
            case 'ResponseCollection':
                $schema->title = 'PrivateConversation';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Response']]),
                ];
                break;

            case 'TimelineItem':
                $schema->title = 'TimelineItem';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'description' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Text']),
                ];
                break;
            case 'Timeline':
                $schema->title = 'PrivateConversation';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/TimelineItem']]),
                ];
                break;

            case 'AreaCollection':
                $schema->title = 'AreaCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Area']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'Area':
                $schema->title = 'Area';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'geo' => $this->buildSchemaProperty(['ref' => '#/components/schemas/Address']),
                ];
                break;
            case 'NewArea':
                $schema->title = 'NewArea';
                $schema->type = 'object';
                $schema->required = ['name'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'geo' => $this->buildSchemaProperty(['ref' => '#/components/schemas/Address']),
                ];
                break;

            case 'CategoryCollection':
                $schema->title = 'CategoryCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Category']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'Category':
                $schema->title = 'Category';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'parent' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'nullable' => true, 'description' => 'ID', 'description' => 'Parent category id']),
                ];
                break;
            case 'NewCategory':
                $schema->title = 'NewCategory';
                $schema->type = 'object';
                $schema->required = ['name'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'parent' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'nullable' => true, 'description' => 'ID', 'description' => 'Parent category id']),
                ];
                break;

            case 'ParticipantCollection':
                $schema->title = 'ParticipantCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Participant']]),
                ];
                break;
            case 'Participant':
                $schema->title = 'Participant';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'role_id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Role Id']),
                    'role_name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Role name']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'last_access_at' => $this->buildSchemaProperty(['type' => 'array', 'description' => 'Operators', 'items' => ['type' => 'integer']]),
                    'type' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Type']),
                ];
                break;

            case 'UserCollection':
                $schema->title = 'UserCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/User']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'User':
                $schema->title = 'User';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                    'last_access_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last access date', 'nullable' => true]),
                    'is_moderated' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Is moderated']),
                    'can_comment' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Can comment']),
                    'can_post_on_behalf_of' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Can post on behalf of others']),
                    'is_enabled' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Is enabled']),
                    'type' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'User type']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'User groups']),
                    'phone' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'User phone']),
                    'is_super_user' => $this->buildSchemaProperty(['type' => 'boolean', 'readOnly' => true, 'description' => 'Is super user']),
                ];
                break;
            case 'NewUser':
                $schema->title = 'NewUser';
                $schema->type = 'object';
                $schema->required = ['first_name', 'last_name', 'email'];
                $schema->properties = [
                    'first_name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'First name']),
                    'last_name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Last name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                    'fiscal_code' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Fiscal Code']),
                    'phone' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Phone']),
                ];
                break;
            case 'PatchUser':
                $schema->title = 'PatchUser';
                $schema->type = 'object';
                $schema->properties = [
                    'first_name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'First name']),
                    'last_name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Last name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                    'fiscal_code' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Fiscal Code']),
                    'phone' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Phone']),
                ];
                break;

            case 'UserGroupCollection':
                $schema->title = 'UserGroupCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/UserGroup']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'UserGroup':
                $schema->title = 'UserGroup';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                ];
                break;
            case 'NewUserGroup':
                $schema->title = 'NewUserGroup';
                $schema->type = 'object';
                $schema->required = ['name'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                ];
                break;

            case 'OperatorCollection':
                $schema->title = 'OperatorCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Operator']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'Operator':
                $schema->title = 'Operator';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                    'last_access_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last access date', 'nullable' => true]),
                    'is_moderated' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Is moderated']),
                    'can_comment' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Can comment']),
                    'can_post_on_behalf_of' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Can post on behalf of others']),
                    'is_enabled' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'Is enabled']),
                    'type' => $this->buildSchemaProperty(['type' => 'boolean', 'description' => 'User type']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'User groups']),
                ];
                break;
            case 'NewOperator':
                $schema->title = 'NewOperator';
                $schema->type = 'object';
                $schema->required = ['first_name', 'last_name', 'email', 'role', 'groups'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                    'role' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Role']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'User groups']),
                ];
                break;

            case 'GroupCollection':
                $schema->title = 'GroupCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Group']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'Group':
                $schema->title = 'Group';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                ];
                break;
            case 'NewGroup':
                $schema->title = 'NewGroup';
                $schema->type = 'object';
                $schema->required = ['name', 'email'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Email', 'format' => 'email']),
                ];
                break;

            case 'StatCollection':
                $schema->title = 'StatCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string']]),
                ];
                break;
            case 'Stat':
                $schema->title = 'Stat';
                $schema->type = 'object';
                $schema->properties = [
                    'identifier' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'data' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                        'intervals' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string']]),
                        'series' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'name' => $this->buildSchemaProperty(['type' => 'string']),
                            'data' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                                'interval' => $this->buildSchemaProperty(['type' => 'string']),
                                'count' => $this->buildSchemaProperty(['type' => 'number']),
                            ]]]),
                        ]]]),
                    ]]),

                ];
                break;
            case 'AvgStat':
                $schema->title = 'AvgStat';
                $schema->type = 'object';
                $schema->properties = [
                    'identifier' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'data' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                        'intervals' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string']]),
                        'series' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'name' => $this->buildSchemaProperty(['type' => 'string']),
                            'data' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                                'interval' => $this->buildSchemaProperty(['type' => 'string']),
                                'count' => $this->buildSchemaProperty(['type' => 'number']),
                                'avg' => $this->buildSchemaProperty(['type' => 'number']),
                            ]]]),
                        ]]]),
                    ]]),

                ];
                break;
            case 'StatusStat':
                $schema->title = 'StatusStat';
                $schema->type = 'object';
                $schema->properties = [
                    'identifier' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Description']),
                    'data' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                        'intervals' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string']]),
                        'series' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'status' => $this->buildSchemaProperty(['type' => 'string']),
                            'percentage' => $this->buildSchemaProperty(['type' => 'number']),
                            'count' => $this->buildSchemaProperty(['type' => 'number']),
                        ]]]),
                    ]]),

                ];
                break;

            case 'FaqCollection':
                $schema->title = 'FaqCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Faq']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'Faq':
                $schema->title = 'Faq';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'description' => 'Unique identifier']),
                    'question' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Question']),
                    'answer' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Answer']),
                    'category' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'description' => 'Category id']),
                    'priority' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'nullable' => true, 'description' => 'Priority']),
                ];
                break;
            case 'NewFaq':
                $schema->title = 'NewFaq';
                $schema->type = 'object';
                $schema->required = ['question', 'answer', 'category'];
                $schema->properties = [
                    'question' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Question']),
                    'answer' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Answer']),
                    'category' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'description' => 'Category id']),
                    'priority' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'nullable' => true, 'description' => 'Priority']),
                ];
                break;

            case 'FeatureCollection':
                $schema->title = 'FeatureCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'type' => $this->buildSchemaProperty(['type' => 'string', 'default' => 'FeatureCollection']),
                    'features' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64']),
                        'geometry' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                            'type' => $this->buildSchemaProperty(['type' => 'string', 'default' => 'Point']),
                            'coordinates' => $this->buildSchemaProperty(['type' => 'array', 'minItems' => 2, 'maxItems' => 3, 'items' => ['type' => 'number']]),
                        ]]),
                        'type' => $this->buildSchemaProperty(['type' => 'string', 'default' => 'Feature']),
                        'properties' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                            'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64']),
                            'type' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                                'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Type label']),
                                'identifier' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Type identifier', 'enum' => $typeEnum]),
                            ]]),
                            'status' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                                'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Status label']),
                                'identifier' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Status identifier', 'enum' => $this->getStatusEnum()]),
                            ]]),
                            'subject' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Subject']),
                            'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Publication date']),
                            'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'description' => 'Last modification date']),
                            'response_count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64']),
                            'comment_count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64']),
                        ]]),
                    ]]]),
                ];
                break;

            case 'TypeCollection':
                $schema->title = 'TypeCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Type']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Next pagination cursor', 'nullable' => true]),
                    'count' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int32', 'description' => 'Total number of items available']),
                ];
                break;
            case 'Type':
                $schema->title = 'Type';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'description' => 'Name']),
                ];
                break;
        }

        return $schema;
    }

    private function buildLink($properties)
    {
        $link = new OA\Link();
        foreach ($properties as $key => $value) {
            $link->{$key} = $value;
        }

        return $link;
    }

    private function getStatusEnum()
    {
        return  ['pending', 'open', 'close']; //@todo
    }

    private function getTypeEnum()
    {
        return  isset($this->postClassDataMap['type']) && $this->postClassDataMap['type']->attribute('data_type_string') == \eZSelectionType::DATA_TYPE_STRING ?
            array_column($this->postClassDataMap['type']->content()['options'], 'name') :
            [];
    }

    private function getChannelEnum()
    {
        return isset($this->postClassDataMap['on_behalf_of_mode']) && $this->postClassDataMap['on_behalf_of_mode']->attribute('data_type_string') == \eZSelectionType::DATA_TYPE_STRING ?
            array_column($this->postClassDataMap['on_behalf_of_mode']->content()['options'], 'name') :
            [];
    }
}
