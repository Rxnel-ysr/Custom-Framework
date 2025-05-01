<?php

use App\Foundation\Http\StaticFile;

require_once __DIR__ . '/App/Foundation/Http/StaticFile.php';

if (StaticFile::serve(__DIR__, $_SERVER['REQUEST_URI'])) {
    return false;
}

$app = require_once __DIR__ . '/App/Core/bootstrap.php';
$app->start();
