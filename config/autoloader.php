<?php

$root = dirname(__DIR__, 1);

return [

    /*
    |--------------------------------------------------------------------------
    | Autoloader Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the autoloader will output detailed logs or errors during
    | class resolution. It's helpful for development but should be disabled
    | in production to avoid unnecessary overhead.
    |
    */

    'debug' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-Resolve Classes
    |--------------------------------------------------------------------------
    |
    | If set to true, the autoloader will attempt to automatically resolve
    | unknown classes using naming conventions or PSR logic. Disabling it
    | forces the loader to rely strictly on the classmap file.
    |
    */

    'auto-resolve' => true,

    'system_scan' => [
        'ignore' => [
            'App/Core/error/',
            'resources/',
            'storage/',
            'config/',
            'public/',
            'routes/',
        ],
        'prioritize' => [
            'App/',
            'test/'
        ],
        'root-scan' => false
    ],

    'psr-4' => [
        'Experimental\\App' => 'App/'
    ],

    'except' => [
        'App\\Foundation\\Http\\Route'
    ],

    'resolution' => [
        'dep' => true,
        'boot' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloader Auto Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the autoloader will output handle cold start and map every
    | class automatically.
    |
    */

    'auto' => true,

    /*
    |--------------------------------------------------------------------------
    | Autoloader Check File Modified Time
    |--------------------------------------------------------------------------
    |
    | When enabled, the autoloader will check the file modified time
    | before including and performing re-scan if time is greater than stored
    | class map
    |
    */

    'check_filemtime' => true,

    /*
    |--------------------------------------------------------------------------
    | Read Only
    |--------------------------------------------------------------------------
    |
    | Force autoloader to only read classmap and not performing any file
    | related action except reading the file.
    |
    */

    'read_only' => false,

    /*
    |--------------------------------------------------------------------------
    | Classmap File Path
    |--------------------------------------------------------------------------
    |
    | This static map allows for blazing-fast class loading.
    | This mapping will be automatically mapped
    |
    */

    'classmap' => $root . '/storage/classes.php',

    /*
    |--------------------------------------------------------------------------
    | Class Cache Path
    |--------------------------------------------------------------------------
    |
    | This file stores the resolved class paths.
    | It acts as a cache to reduce file system scans and boost performance.
    |
    */

    'cache' => $root . '/storage/cache/classes/classes.php',

    /*
    |--------------------------------------------------------------------------
    | Class look dir
    |--------------------------------------------------------------------------
    |
    | Define a path to autoloader to search class on,
    | E.g. defined 'App/Foundation' and class that looked is aClass
    | will be searched in 'App/Foundation/aClass.php'
    |
    */

    'where_to_look_class' => 'App/Foundation',

    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    |
    | List of files that will always be loaded
    |
    */

    'files' => [
        'App/Core/Routers/RouterInterface.php',
        'App/Foundation/Helpers/Utility.php',
        'App/Foundation/Helpers/Helpers.php'
    ],


    'additional_methods' => []
];
