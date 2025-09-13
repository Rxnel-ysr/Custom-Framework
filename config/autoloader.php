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

    'auto-resolve' => false,

    /*
    |--------------------------------------------------------------------------
    | Classmap File Path
    |--------------------------------------------------------------------------
    |
    | This static map allows for blazing-fast class loading.
    | This mapping will be automatically mapped
    |
    */

    'classmap' => $root . '/config/classes.php',

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

    'where_to_look_class' => '/App/Foundation',
    /*
    |--------------------------------------------------------------------------
    | Autoloader Auto Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the autoloader will output handle cold start and map every
    | class automatically.
    */
    'auto' => true,

    'files' => [
        'App/Core/Routers/RouterInterface.php',
        'App/Foundation/Helpers/Utility.php',
        'App/Foundation/Helpers/Helpers.php'
    ]
];
