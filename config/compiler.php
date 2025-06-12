<?php

$root = dirname(__DIR__, 1);

return [

    /*
    |--------------------------------------------------------------------------
    | Template File Extension
    |--------------------------------------------------------------------------
    |
    | This value defines the custom extension used for your view templates.
    | Instead of the standard .php, templates will end with
    | the extension defined here.
    |
    */

    'ext' => '.rx.php',

    /*
    |--------------------------------------------------------------------------
    | View Templates Path
    |--------------------------------------------------------------------------
    |
    | This directory contains all of your raw view/template files. These files
    | will be compiled and cached by the template engine for faster rendering.
    |
    */

    'views' => $root . '/resources/views',

    /*
    |--------------------------------------------------------------------------
    | Compiled Views Cache Path
    |--------------------------------------------------------------------------
    |
    | This directory is used to store the compiled version of your views.
    | Storing compiled views improves performance by avoiding re-parsing
    | the original templates on every request.
    |
    */

    'cache' => $root . '/storage/cache/views',
];
