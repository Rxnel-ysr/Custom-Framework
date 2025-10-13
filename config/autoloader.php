<?php

use App\Foundation\Generator\TemplateBuilder;

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

    'debug' => true,

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

    /*
    |--------------------------------------------------------------------------
    | Classmap File Path
    |--------------------------------------------------------------------------
    |
    | This static map allows for blazing-fast class loading.
    | This mapping will be automatically mapped
    |
    */

    'classmap' => $root . '/config/classes_EXPE.php',

    /*
    |--------------------------------------------------------------------------
    | Class Cache Path
    |--------------------------------------------------------------------------
    |
    | This file stores the resolved class paths.
    | It acts as a cache to reduce file system scans and boost performance.
    |
    */

    'cache' => $root . '/storage/cache/classes/classes_EXPE.php',

    /*
    |--------------------------------------------------------------------------
    | Class look dir
    |--------------------------------------------------------------------------
    |
    | Define a path to autoloader to search class on,
    | E.g. defined 'App/Foundation' and class that looked is aClass
    | will be searched in 'App/Foundation/aClass.php'
    */
    'where_to_look_class' => 'App/Foundation',

    /*
    |--------------------------------------------------------------------------
    | Autoloader Auto Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the autoloader will output handle cold start and map every
    | class automatically.
    */
    'auto' => true,


    /*
    |--------------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------------
    |
    | List of files that will always be loaded
    */
    'files' => [
        'App/Core/Routers/RouterInterface.php',
        'App/Foundation/Helpers/Utility.php',
        'App/Foundation/Helpers/Helpers.php'
    ],


    'additional_methods' => [
        function ($class) use ($root) {
            $hasNamespace = strpos($class, '\\') !== false;

            if ($hasNamespace) {
                $normalized = str_replace('\\', '/', $class);
                $namespace = str_replace('/', '\\', dirname($normalized));
                $classname = basename($normalized);


                $classBuilder = new TemplateBuilder(
                    "{$root}/storage/templates/classWithNamespace_placeholder.stub"
                )->rules([
                    'namespace' => $namespace,
                    'classname' => $classname
                ])->parse();
            } else {
                $classBuilder = new TemplateBuilder(
                    "{$root}/storage/templates/class_placeholder.stub"
                )->rules([
                    'classname' => $class
                ])->parse();
            }

            eval($classBuilder->getResult());

            return true;
        }
    ]
];
