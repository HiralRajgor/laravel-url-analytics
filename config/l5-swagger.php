<?php

return [
    'default' => 'default',

    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'URL Analytics API',
            ],

            'routes' => [
                'api'  => 'api/documentation',
                'docs' => 'docs',
            ],

            'paths' => [
                'use_absolute_path'      => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),
                'docs_json'              => 'api-docs.json',
                'docs_yaml'              => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                'annotations'            => [
                    base_path('app'),
                ],
                'excludes'               => [],
                'base'                   => env('L5_SWAGGER_BASE_PATH', null),
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
                'docs'                   => storage_path('api-docs'),
                'views'                  => base_path('resources/views/vendor/l5-swagger'),
                'asset_path'             => public_path('vendor/l5-swagger'),
            ],

            'scanOptions' => [
                'analyser'              => null,
                'analysis'              => null,
                'processors'            => [],
                'pattern'               => null,
                'exclude'               => [],
                'open_api_spec_version' => '3.0.0',
            ],

            'securityDefinitions' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'Token',
                    ],
                ],
                'security' => [],
            ],

            'generate_always'    => env('L5_SWAGGER_GENERATE_ALWAYS', false),
            'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

            'proxy' => false,

            'additional_config_url' => null,

            'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', 'alpha'),

            'validator_url' => null,

            'ui' => [
                'display' => [
                    'doc_expansion'          => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                    'filter'                 => env('L5_SWAGGER_UI_FILTERS', true),
                    'show_extensions'        => env('L5_SWAGGER_UI_SHOW_EXTENSIONS', false),
                    'show_common_extensions' => env('L5_SWAGGER_UI_SHOW_COMMON_EXTENSIONS', false),
                    'try_it_out_enabled'     => env('L5_SWAGGER_UI_TRY_IT_OUT_ENABLED', true),
                ],

                'authorization' => [
                    'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
                    'oauth2RedirectUrl'     => env('L5_SWAGGER_UI_OAUTH2_REDIRECT_URL', '/api/oauth2-callback'),

                    'oauth2' => [
                        'use_pkce_with_authorization_code_grant' => false,
                    ],
                ],
            ],

            'constants' => [
                'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000'),
            ],

            'headers' => [],
        ],
    ],

    'defaults' => [
        'routes' => [
            'docs'            => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware'      => [
                'api'    => [],
                'asset'  => [],
                'docs'   => [],
                'oauth2' => [],
            ],
            'group_options' => [],
        ],

        'paths' => [
            'docs'                   => storage_path('api-docs'),
            'views'                  => base_path('resources/views/vendor/l5-swagger'),
            'base'                   => env('L5_SWAGGER_BASE_PATH', null),
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
            'excludes'               => [],
        ],

        'scanOptions' => [
            'analyser'              => null,
            'analysis'              => null,
            'processors'            => [],
            'pattern'               => null,
            'exclude'               => [],
            'open_api_spec_version' => env('SWAGGER_VERSION', '3.0.0'),
        ],

        'securityDefinitions' => [
            'securitySchemes' => [],
            'security'        => [],
        ],

        'generate_always'       => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy'    => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        'proxy'                 => false,
        'additional_config_url' => null,
        'operations_sort'       => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'validator_url'         => null,

        'ui' => [
            'display' => [
                'doc_expansion'          => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                'filter'                 => env('L5_SWAGGER_UI_FILTERS', true),
                'show_extensions'        => env('L5_SWAGGER_UI_SHOW_EXTENSIONS', false),
                'show_common_extensions' => env('L5_SWAGGER_UI_SHOW_COMMON_EXTENSIONS', false),
                'try_it_out_enabled'     => env('L5_SWAGGER_UI_TRY_IT_OUT_ENABLED', false),
            ],

            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
                'oauth2RedirectUrl'     => env('L5_SWAGGER_UI_OAUTH2_REDIRECT_URL', '/api/oauth2-callback'),
                'oauth2'                => [
                    'use_pkce_with_authorization_code_grant' => false,
                ],
            ],
        ],

        'constants' => [],
        'headers'   => [],
    ],
];
