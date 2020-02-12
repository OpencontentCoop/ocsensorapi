<?php

namespace Opencontent\Sensor\OpenApi;

use Opencontent\Sensor\Api\StatisticFactory;
use Opencontent\Sensor\OpenApi;
use erasys\OpenApi as OpenApiBase;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\Sensor\Legacy\SearchService;

class SchemaBuilder
{
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
        'posts' => "Posts",
        'post-operators' => "Operators of post",
        'post-statuses' => "Statuses of post",
        'users' => "Users",
        'operators' => "Operators",
        'groups' => "Groups of operators",
        'categories' => "Categories",
        'areas' => "Areas",
        'stat' => "Statistics",
    ];

    public function __construct(OpenApi $apiSettings)
    {
        $this->apiSettings = $apiSettings;
        $this->siteIni = \eZINI::instance();
        $this->postClass = $this->apiSettings->getRepository()->getPostContentClass();
        $this->postClassDataMap = $this->postClass->dataMap();
    }

    /**
     * return OA\Document
     */
    public function build()
    {
        $document = new OA\Document(
            $this->buildInfo(),
            array_merge(
                $this->buildPostPaths(),
                $this->buildUserPaths(),
                $this->buildOperatorPaths(),
                $this->buildGroupPaths(),
                $this->buildCategoryPaths(),
                $this->buildAreaPaths(),
                $this->buildStatisticPaths()
            ),
            '3.0.1',
            [
                'servers' => $this->buildServers(),
                'tags' => $this->buildTags(),
                'components' => $this->buildComponents(),
                'security' => [['basicAuth' => []]]
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
                'xApiId' => new OpenApiBase\ExtensionProperty('api-id', 'be447a40-ee4f-11e9-81b4-2a2ae2dbcce4'),

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
        return [
            new OA\Tag(self::$tags['posts']),
            new OA\Tag(self::$tags['post-operators']),
            new OA\Tag(self::$tags['post-statuses']),
            new OA\Tag(self::$tags['users']),
            new OA\Tag(self::$tags['operators']),
            new OA\Tag(self::$tags['groups']),
            new OA\Tag(self::$tags['categories']),
            new OA\Tag(self::$tags['areas']),
            new OA\Tag(self::$tags['stat']),
        ];
    }

    private function buildPostPaths()
    {
        return [
            '/posts' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/PostCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid search limit provided.'),
                    ],
                    'loadPosts',
                    'Get all posts',
                    [
                        'description' => 'Returns a list of post',
                        'tags' => [self::$tags['posts']],
                        'parameters' => $this->buildSearchParameters()
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '405' => new OA\Response('Invalid input'),
                    ],
                    'createPost',
                    'Add a new post',
                    [
                        'summary' => 'Returns a list of post',
                        'tags' => [self::$tags['posts']],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Post')
                    ]
                ),
            ]),
            '/posts/{postId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ]],
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
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Post')
                            ]],
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
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\Reference('#/components/requestBodies/Post')
                    ]
                ),
            ]),
            '/posts/{postId}/approvers' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getApproversByPostId',
                    'Get post approvers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setApproversToPostId',
                    'Set post approvers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['participant_ids' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ]], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/owners' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getOwnersByPostId',
                    'Get post owners',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setOwnersToPostId',
                    'Set post owners',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['participant_ids' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ]], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/observers' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getObserversByPostId',
                    'Get post observers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setObserversToPostId',
                    'Set post observers',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['participant_ids' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ]], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/participants' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ParticipantCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getParticipantsByPostId',
                    'Get all participants',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
            ]),
            '/posts/{postId}/participants/{participantId}/users' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/UserCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPostParticipantUsersByParticipantId',
                    'Get user in participant',
                    [
                        'tags' => [self::$tags['post-operators']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                            new OA\Parameter('participantId', OA\Parameter::IN_PATH, 'ID of participant', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
            ]),
            '/posts/{postId}/comments' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/PublicConversation')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCommentsByPostId',
                    'Get post comments',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Comment')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid format provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addCommentsToPostId',
                    'Add post comment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text'])]])
                        ]], 'Comment text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/comments/{commentId}' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Comment')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'editCommentsInPostId',
                    'Edit post comment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                            new OA\Parameter('commentId', OA\Parameter::IN_PATH, 'ID of comment to edit', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text'])]])
                        ]], 'Comment text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/privateMessages' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/PrivateConversation')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPrivateMessagesByPostId',
                    'Get post private messages',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/PrivateMessage')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addPrivateMessageToPostId',
                    'Add post private message',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                                'text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text']),
                                'receivers' => $this->buildSchemaProperty(['type' => 'array', 'items' => $this->buildSchemaProperty(['type' => 'integer'])]),
                            ]])
                        ]], 'Message text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/privateMessages/{privateMessageId}' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/PrivateMessage')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'editPrivateMessageInPostId',
                    'Edit post private message',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                            new OA\Parameter('privateMessageId', OA\Parameter::IN_PATH, 'ID of private message to edit', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text'])]])
                        ]], 'Message text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/responses' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/ResponseCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getResponsesByPostId',
                    'Get post responses',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Response')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addResponsesToPostId',
                    'Add post response',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text'])]])
                        ]], 'Response text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/responses/{responseId}' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Response')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'editResponsesInPostId',
                    'Edit post response',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                            new OA\Parameter('responseId', OA\Parameter::IN_PATH, 'ID of response to edit', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text'])]])
                        ]], 'Response text', true)
                    ]
                ),
            ]),
            '/posts/{postId}/attachments' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/AttachmentCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getAttachmentsByPostId',
                    'Get post attachments',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'post' => new OA\Operation(
                    [
                        '201' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Attachment')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'addAttachmentsToPostId',
                    'Add post attachment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['files' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Attachment']])]])
                        ]], 'Attachments', true)
                    ]
                ),
            ]),
            '/posts/{postId}/attachments/{filename}' => new OA\PathItem([
                'delete' => new OA\Operation(
                    [
                        '204' => new OA\Response('Successful response'),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'deleteAttachmentsInPostId',
                    'Delete post attachment',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                            new OA\Parameter('filename', OA\Parameter::IN_PATH, 'Filename of attachment to remove', [
                                'schema' => $this->buildSchemaProperty(['type' => 'string']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
            ]),
            '/posts/{postId}/timeline' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Timeline')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getTimelineByPostId',
                    'Get post timeline',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
            ]),
            '/posts/{postId}/areas' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/AreaCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getAreasByPostId',
                    'Get post areas',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/AreaCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setAreasToPostId',
                    'Set post areas',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['area_id' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ]], 'Area id list', true)
                    ]
                ),
            ]),
            '/posts/{postId}/categories' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/CategoryCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getCategoriesByPostId',
                    'Get post categories',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/CategoryCollection')
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setCategoriesToPostId',
                    'Set post categories',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['category_id' => $this->buildSchemaProperty(['type' => 'array', 'maximum' => 1, 'items' => $this->buildSchemaProperty(['type' => 'integer'])])]])
                        ]], 'User id', true)
                    ]
                ),
            ]),
            '/posts/{postId}/status' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getStatusByPostId',
                    'Get post status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
            ]),
            '/posts/{postId}/workflowStatus' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getWorkflowStatusByPostId',
                    'Get post workflow status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setWorkflowStatusByPostId',
                    'Set post workflow status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string', 'enum' => ['fixed', 'closed']])]])
                        ]], 'Workflow status identifier', true)
                    ]
                ),
            ]),
            '/posts/{postId}/privacyStatus' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getPrivacyStatusByPostId',
                    'Get post privacy status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setPrivacyStatusByPostId',
                    'Set post privacy status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string', 'enum' => ['public', 'private']])]])
                        ]], 'Privacy status identifier', true)
                    ]
                ),
            ]),
            '/posts/{postId}/moderationStatus' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getModerationStatusByPostId',
                    'Get post moderation status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ]
                    ]
                ),
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setModerationStatusByPostId',
                    'Set post moderation status',
                    [
                        'tags' => [self::$tags['post-statuses']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['identifier' => $this->buildSchemaProperty(['type' => 'string', 'enum' => ['waiting', 'accepted', 'refused']])]])
                        ]], 'Moderation status identifier', true)
                    ]
                ),
            ]),
            '/posts/{postId}/expiry' => new OA\PathItem([
                'put' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response', [
                            'application/json' => [
                                'schema' => $this->buildSchemaProperty(['type' => 'object', 'properties' => ['expiry_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Expiration date'])]])
                            ]
                        ]),
                        '400' => new OA\Response('Invalid input provided'),
                        '403' => new OA\Response('Forbidden'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'setExpiryByPostId',
                    'Set post expiration date',
                    [
                        'tags' => [self::$tags['posts']],
                        'parameters' => [
                            new OA\Parameter('postId', OA\Parameter::IN_PATH, 'ID of post', [
                                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
                                'required' => true,
                            ]),
                        ],
                        'requestBody' => new OA\RequestBody(['application/json' => [
                            'schema' => $this->buildSchemaProperty(['type' => 'integer', 'minimum' => 1])
                        ]], 'Expiration days since post creation date', true)
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/UserCollection')
                            ]
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ]
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
            '/users/{userId}' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response',
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ]], null),
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/User')
                            ]], null),
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
            ]),
        ];
    }

    private function buildOperatorPaths()
    {
        return [
            '/operators' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/OperatorCollection')
                            ]
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Operator')
                            ]
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Operator')
                            ]], null),
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Operator')
                            ]], null),
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/GroupCollection')
                            ]
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ]
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ]], null),
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ]], null),
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Group')
                            ]], null),
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/CategoryCollection')
                            ]
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Category')
                            ]
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Category')
                            ]], null),
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Category')
                            ]], null),
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/AreaCollection')
                            ]
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
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Area')
                            ]
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Area')
                            ]], null),
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Area')
                            ]], null),
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

    private function buildStatisticPaths()
    {
        return [
            '/stats' => new OA\PathItem([
                'get' => new OA\Operation(
                    [
                        '200' => new OA\Response('Successful response.', [
                            'application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/StatCollection')
                            ]
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
                            ['application/json' => [
                                'schema' => new OA\Reference('#/components/schemas/Stat')
                            ]], null),
                        '400' => new OA\Response('Invalid input provided'),
                        '404' => new OA\Response('Not found'),
                    ],
                    'getStatByIdentifier',
                    'Get single stat data',
                    [
                        'tags' => [self::$tags['stat']],
                        'parameters' => [
                            new OA\Parameter('statIdentifier', OA\Parameter::IN_PATH, 'ID of stat', [
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

    private function buildSearchParameters()
    {
        return [
            new OA\Parameter('q', OA\Parameter::IN_QUERY, 'Query parameter', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'nullable' => true]),
            ]),
            new OA\Parameter('limit', OA\Parameter::IN_QUERY, 'Limit to restrict the number of entries on a page', [
                'schema' => $this->buildSchemaProperty(['type' => 'integer', 'minimum' => 1, 'maximum' => SearchService::MAX_LIMIT, 'default' => SearchService::DEFAULT_LIMIT, 'nullable' => true]),
            ]),
            new OA\Parameter('offset', OA\Parameter::IN_QUERY, 'Numeric offset of the first element provided on a page representing a collection request', [
                'schema' => $this->buildSchemaProperty(['type' => 'integer']),
            ]),
            new OA\Parameter('cursor', OA\Parameter::IN_QUERY, 'Cursor pagination', [
                'schema' => $this->buildSchemaProperty(['type' => 'string', 'default' => '*', 'nullable' => true]),
            ])
        ];
    }

    private function buildComponents()
    {
        $components = new OA\Components();

        //@todo @see https://opensource.zalando.com/restful-api-guidelines/#104
        $components->securitySchemes = [
            'basicAuth' => new OA\SecurityScheme('http', null, ['scheme' => 'basic']),
        ];

        $components->schemas = [
            'PostCollection' => $this->buildSchema('PostCollection'),
            'NewPost' => $this->buildSchema('NewPost'),
            'Post' => $this->buildSchema('Post'),

            'Address' => $this->buildSchema('Address'),
            'Image' => $this->buildSchema('Image'),
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

            'OperatorCollection' => $this->buildSchema('OperatorCollection'),
            'Operator' => $this->buildSchema('Operator'),
            'NewOperator' => $this->buildSchema('NewOperator'),

            'GroupCollection' => $this->buildSchema('GroupCollection'),
            'Group' => $this->buildSchema('Group'),
            'NewGroup' => $this->buildSchema('NewGroup'),

            'StatCollection' => $this->buildSchema('StatCollection'),
            'Stat' => $this->buildSchema('Stat'),
        ];

        $components->requestBodies = [
            'Post' => new OA\RequestBody(['application/json' => [
                'schema' => new OA\Reference('#/components/schemas/NewPost')
            ]], 'Post object that needs to be added or updated', true),

            'User' => new OA\RequestBody(['application/json' => [
                'schema' => new OA\Reference('#/components/schemas/NewUser')
            ]], 'User object that needs to be added or updated', true),

            'Operator' => new OA\RequestBody(['application/json' => [
                'schema' => new OA\Reference('#/components/schemas/NewOperator')
            ]], 'Operator object that needs to be added or updated', true),

            'Group' => new OA\RequestBody(['application/json' => [
                'schema' => new OA\Reference('#/components/schemas/NewGroup')
            ]], 'Group object that needs to be added or updated', true),

            'Area' => new OA\RequestBody(['application/json' => [
                'schema' => new OA\Reference('#/components/schemas/NewArea')
            ]], 'Area object that needs to be added or updated', true),

            'Category' => new OA\RequestBody(['application/json' => [
                'schema' => new OA\Reference('#/components/schemas/NewCategory')
            ]], 'Category object that needs to be added or updated', true),
        ];

        return $components;
    }

    private function buildSchema($schemaName)
    {
        $schema = new OA\Schema();

        $typeEnum = isset($this->postClassDataMap['type']) && $this->postClassDataMap['type']->attribute('data_type_string') == \eZSelectionType::DATA_TYPE_STRING ?
            array_column($this->postClassDataMap['type']->content()['options'], 'name') :
            [];

        switch ($schemaName) {
            case 'Address':
                $schema->title = 'Address';
                $schema->type = 'object';
                $schema->properties = [
                    'address' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Address']),
                    'longitude' => $this->buildSchemaProperty(['type' => 'number', 'title' => 'Longitude']),
                    'latitude' => $this->buildSchemaProperty(['type' => 'number', 'title' => 'Latitude']),
                ];
                break;
            case 'Image':
                $schema->title = 'Image';
                $schema->type = 'object';
                $schema->properties = [
                    'filename' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'File name']),
                    'file' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'byte', 'title' => 'base64-encoded file contents']),
                ];
                break;
            case 'Attachment':
                $schema->title = 'Attachment';
                $schema->type = 'object';
                $schema->properties = [
                    'filename' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'File name']),
                    'file' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'byte', 'title' => 'base64-encoded file contents']),
                ];
                break;
            case 'AttachmentCollection':
                $schema->title = 'PublicConversation';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'url', 'title' => 'File'])]),
                ];
                break;

            case 'PostCollection':
                $schema->title = 'PostCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Post']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Next pagination cursor', 'nullable' => true]),
                ];
                break;
            case 'NewPost':
                $schema->title = 'NewPost';
                $postClass = $this->apiSettings->getRepository()->getPostContentClass();
                /**
                 * @var string $identifier
                 * @var \eZContentClassAttribute $attribute
                 */

                foreach ($this->postClassDataMap as $identifier => $attribute) {
                    if ($attribute->attribute('category') == 'content' || $attribute->attribute('category') == '') {

                        $properties = [
                            'title' => $attribute->attribute('name'),
                            'type' => 'string'
                        ];

                        if ($identifier == 'geo') {
                            $identifier = 'address';
                            $properties = [
                                'title' => $attribute->attribute('name'),
                                'ref' => '#/components/schemas/Address'
                            ];
                        }

                        if ($identifier == 'area') {
                            $properties = [
                                'type' => 'integer',
                                'maximum' => 1,
                                'nullable' => true
                            ];
                        }

                        if ($identifier == 'category') {
                            $properties = [
                                'type' => 'integer',
                                'maximum' => 1,
                                'nullable' => true
                            ];
                        }

                        if ($identifier == 'image') {
                            $properties = [
                                'title' => $attribute->attribute('name'),
                                'ref' => '#/components/schemas/Image'
                            ];
                        }

                        if ($identifier == 'type') {
                            $properties['enum'] = $typeEnum;
                            $properties['default'] = $typeEnum[0];

                        }

                        if ($identifier == 'privacy') {
                            $identifier = 'is_private';
                            $properties = [
                                'type' => 'boolean',
                                'default' => false
                            ];
                        }

                        $schema->properties[$identifier] = $this->buildSchemaProperty($properties);
                        if ($attribute->attribute('is_required')) {
                            $schema->required[] = $identifier;
                        }
                    }
                }
                break;
            case 'Post':
                $schema->title = 'Post';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last modification date']),
                    'expiry_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Expiration date', 'nullable' => true]),
                    'closed_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Closing date', 'nullable' => true]),
                    'subject' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Subject']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Description']),
                    'address' => $this->buildSchemaProperty(['ref' => '#/components/schemas/Address']),
                    'type' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Type', 'enum' => $typeEnum]),
                    'status' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Status', 'enum' => ['pending', 'open', 'close']]),
                    'privacy_status' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Privacy status', 'enum' => ['public', 'private']]),
                    'moderation_status' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Moderation status', 'enum' => ['waiting', 'approved']]),
                    'author' => $this->buildSchemaProperty(['type' => 'integer', 'title' => 'Author']),
                    'reporter' => $this->buildSchemaProperty(['type' => 'integer', 'title' => 'Reporter']),
                    'image' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'url', 'title' => 'Image', 'nullable' => true]),
                    'is_comments_allowed' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Comments allowed']),
                    'address_meta_info' => $this->buildSchemaProperty(['type' => 'object', 'title' => 'Key/value meta informations about geo location']),
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
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'title' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text']),
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
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'title' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text']),
                    'receivers' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Receivers', 'items' => ['type' => 'integer']]),
                ];
                break;

            case 'Response':
                $schema->title = 'Response';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'title' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text']),
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
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'Post ID', 'description' => 'Unique identifier']),
                    'published_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Publication date']),
                    'modified_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last modification date']),
                    'creator' => $this->buildSchemaProperty(['type' => 'integer', 'title' => 'Creator']),
                    'text' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Text']),
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
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Next pagination cursor', 'nullable' => true]),
                ];
                break;
            case 'Area':
                $schema->title = 'Area';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'geo' => $this->buildSchemaProperty(['ref' => '#/components/schemas/Address']),
                    'operators' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Operators', 'items' => ['type' => 'integer']]),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Groups', 'items' => ['type' => 'integer']]),
                ];
                break;
            case 'NewArea':
                $schema->title = 'NewArea';
                $schema->type = 'object';
                $schema->required = ['name'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'operators' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Operators', 'items' => ['type' => 'integer']]),
                    'geo' => $this->buildSchemaProperty(['ref' => '#/components/schemas/Address']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Groups', 'items' => ['type' => 'integer']]),
                ];
                break;

            case 'CategoryCollection':
                $schema->title = 'CategoryCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Category']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Next pagination cursor', 'nullable' => true]),
                ];
                break;
            case 'Category':
                $schema->title = 'Category';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'operators' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Operators', 'items' => ['type' => 'integer']]),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Groups', 'items' => ['type' => 'integer']]),
                ];
                break;
            case 'NewCategory':
                $schema->title = 'NewCategory';
                $schema->type = 'object';
                $schema->required = ['name'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'operators' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Operators', 'items' => ['type' => 'integer']]),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Groups', 'items' => ['type' => 'integer']]),
                    'parent' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'nullable' => true, 'title' => 'ID', 'description' => 'Parent category id']),
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
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'ID', 'description' => 'Unique identifier']),
                    'role_id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'Role Id']),
                    'role_name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Role name']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Description']),
                    'last_access_at' => $this->buildSchemaProperty(['type' => 'array', 'title' => 'Operators', 'items' => ['type' => 'integer']]),
                    'type' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Type']),
                ];
                break;

            case 'UserCollection':
                $schema->title = 'UserCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/User']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Next pagination cursor', 'nullable' => true]),
                ];
                break;
            case 'User':
                $schema->title = 'User';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Description']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Email', 'format' => 'email']),
                    'last_access_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last access date', 'nullable' => true]),
                    'is_moderated' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Is moderated']),
                    'can_comment' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Can comment']),
                    'can_post_on_behalf_of' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Can post on behalf of others']),
                    'is_enabled' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Is enabled']),
                    'type' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'User type']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'title' => 'User groups']),
                ];
                break;
            case 'NewUser':
                $schema->title = 'NewUser';
                $schema->type = 'object';
                $schema->required = ['first_name', 'last_name', 'email'];
                $schema->properties = [
                    'first_name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'First name']),
                    'last_name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Last name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Email', 'format' => 'email']),
                ];
                break;

            case 'OperatorCollection':
                $schema->title = 'OperatorCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Operator']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Next pagination cursor', 'nullable' => true]),
                ];
                break;
            case 'Operator':
                $schema->title = 'Operator';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Description']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Email', 'format' => 'email']),
                    'last_access_at' => $this->buildSchemaProperty(['type' => 'string', 'format' => 'date-time', 'title' => 'Last access date', 'nullable' => true]),
                    'is_moderated' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Is moderated']),
                    'can_comment' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Can comment']),
                    'can_post_on_behalf_of' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Can post on behalf of others']),
                    'is_enabled' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'Is enabled']),
                    'type' => $this->buildSchemaProperty(['type' => 'boolean', 'title' => 'User type']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'title' => 'User groups']),
                ];
                break;
            case 'NewOperator':
                $schema->title = 'NewOperator';
                $schema->type = 'object';
                $schema->required = ['first_name', 'last_name', 'email', 'role', 'groups'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Email', 'format' => 'email']),
                    'role' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Role']),
                    'groups' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'integer'], 'title' => 'User groups']),
                ];
                break;

            case 'GroupCollection':
                $schema->title = 'GroupCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['ref' => '#/components/schemas/Group']]),
                    'self' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Current pagination cursor']),
                    'next' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Next pagination cursor', 'nullable' => true]),
                ];
                break;
            case 'Group':
                $schema->title = 'Group';
                $schema->type = 'object';
                $schema->properties = [
                    'id' => $this->buildSchemaProperty(['type' => 'integer', 'format' => 'int64', 'readOnly' => true, 'title' => 'ID', 'description' => 'Unique identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Email', 'format' => 'email']),
                ];
                break;
            case 'NewGroup':
                $schema->title = 'NewGroup';
                $schema->type = 'object';
                $schema->required = ['name', 'email'];
                $schema->properties = [
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'email' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Email', 'format' => 'email']),
                ];
                break;

            case 'StatCollection':
                $schema->title = 'GroupCollection';
                $schema->type = 'object';
                $schema->properties = [
                    'items' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string']]),
                ];
                break;
            case 'Stat':
                $schema->title = 'Stat';
                $schema->type = 'object';
                $schema->properties = [
                    'identifier' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Identifier']),
                    'name' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Name']),
                    'description' => $this->buildSchemaProperty(['type' => 'string', 'title' => 'Description']),
                    'data' => $this->buildSchemaProperty(['type' => 'object', 'properties' => [
                        'intervals' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'string']]),
                        'series' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                            'name' => $this->buildSchemaProperty(['type' => 'string']),
                            'data' => $this->buildSchemaProperty(['type' => 'array', 'items' => ['type' => 'object']]),
                        ]]]),
                    ]]),

                ];
                break;
        }

        return $schema;
    }

    private function buildSchemaProperty($properties)
    {
        $schema = new ReferenceSchema();
        foreach ($properties as $key => $value) {
            $schema->{$key} = $value;
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
}