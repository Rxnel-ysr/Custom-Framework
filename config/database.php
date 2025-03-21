<?php
return [
    'default' => 'sqlite',
    'sqlite' => [
        'database' => DATABASE . 'database.sqlite'
    ],
    'mysql' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci')
    ]
];
