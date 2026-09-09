<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Debugbar Settings
     |--------------------------------------------------------------------------
     |
     | Debugbar is enabled by default, when debug is set to true in app.php.
     | You can override the value by setting enable to true or false instead of null.
     |
     | You can provide an array of URI's that must be ignored (eg. 'api/*')
     |
     */

    'enabled' => env('DEBUGBAR_ENABLED', null),
    'except' => [
        'telescope*',
        'horizon*',
    ],

    /*
     |--------------------------------------------------------------------------
     | Storage settings
     |--------------------------------------------------------------------------
     |
     | Debugbar stores data for session/ajax requests.
     | You can disable this, so the debugbar stores data in headers/session,
     | but this can cause problems with large data collectors.
     | By default, file storage (in the storage folder) is used.
     | Redis and PDO can also be used.
     */
    'storage' => [
        'enabled' => env('DEBUGBAR_STORAGE_ENABLED', true),
        'open' => env('DEBUGBAR_OPEN_STORAGE'),
        'driver' => env('DEBUGBAR_STORAGE_DRIVER', 'redis'),
        'path' => env('DEBUGBAR_STORAGE_PATH', storage_path('debugbar')),
        'connection' => env('DEBUGBAR_STORAGE_CONNECTION'),
        'provider' => env('DEBUGBAR_STORAGE_PROVIDER', ''),
    ],

    /*
     |--------------------------------------------------------------------------
     | Force Allow Debugbar to be Enabled during boot
     |--------------------------------------------------------------------------
     */
    'force_allow_enable' => env('DEBUGBAR_FORCE_ALLOW_ENABLE', false),

    /*
     |--------------------------------------------------------------------------
     | Vendors
     |--------------------------------------------------------------------------
     */
    'use_dist_files' => env('DEBUGBAR_USE_DIST_FILES', true),
    'include_vendors' => env('DEBUGBAR_INCLUDE_VENDORS', true),

    /*
     |--------------------------------------------------------------------------
     | Custom Error Handler for Deprecated warnings
     |--------------------------------------------------------------------------
     */
    'error_handler' => env('DEBUGBAR_ERROR_HANDLER', false),
    'error_level' => env('DEBUGBAR_ERROR_LEVEL', E_ALL),

    /*
     |--------------------------------------------------------------------------
     | Clockwork integration
     |--------------------------------------------------------------------------
     */
    'clockwork' => env('DEBUGBAR_CLOCKWORK', false),

    /*
     |--------------------------------------------------------------------------
     | DataCollectors
     |--------------------------------------------------------------------------
     */
    'collectors' => [
        'phpinfo' => true,
        'messages' => true,
        'time' => true,
        'memory' => true,
        'exceptions' => true,
        'log' => true,
        'db' => true,
        'views' => true,
        'route' => true,
        'auth' => false,
        'gate' => true,
        'session' => true,
        'symfony_request' => true,
        'mail' => true,
        'laravel' => false,
        'events' => false,
        'default_request' => false,
        'logs' => false,
        'files' => false,
        'config' => false,
        'cache' => false,
        'models' => false,
    ],

    /*
     |--------------------------------------------------------------------------
     | Extra options
     |--------------------------------------------------------------------------
     */
    'options' => [
        'auth' => [
            'show_name' => true,
        ],
        'db' => [
            'with_params' => true,
            'backtrace' => true,
            'timeline' => false,
            'explain' => [
                'enabled' => false,
                'types' => ['SELECT'],
            ],
            'hints' => true,
        ],
        'mail' => [
            'full_log' => false,
        ],
        'views' => [
            'data' => false,
        ],
        'route' => [
            'label' => true,
        ],
        'logs' => [
            'file' => null,
        ],
        'cache' => [
            'values' => true,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Inject Debugbar in Response
     |--------------------------------------------------------------------------
     */
    'inject' => env('DEBUGBAR_INJECT', true),

    /*
     |--------------------------------------------------------------------------
     | Debugbar route prefix & domain
     |--------------------------------------------------------------------------
     */
    'route_prefix' => env('DEBUGBAR_ROUTE_PREFIX', '_debugbar'),
    'route_middleware' => [],
    'route_domain' => env('DEBUGBAR_ROUTE_DOMAIN'),

    /*
     |--------------------------------------------------------------------------
     | Debugbar theme & limits
     |--------------------------------------------------------------------------
     */
    'theme' => env('DEBUGBAR_THEME', 'auto'),
    'debug_backtrace_limit' => (int) env('DEBUGBAR_DEBUG_BACKTRACE_LIMIT', 50),
];
