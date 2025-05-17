<?php
$root = dirname(__DIR__, 1);

return [
    'default' => 'mysql',
    'sqlite' => [
        'database' =>  $root . '/database/database.sqlite'
    ],
    'mysql' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'name' => env('DB_NAME', 'Native-php'),
        'user' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci')
    ]
];
