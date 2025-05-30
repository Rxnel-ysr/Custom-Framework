<?php

declare(strict_types=1);
define('START', hrtime(true));

use App\Foundation\Http\{StaticFile, Request};

require __DIR__ . '/App/Foundation/Http/StaticFile.php';

if (StaticFile::serve(__DIR__, $_SERVER['REQUEST_URI'], __DIR__ . '/storage/cache')) {
    exit;
}

(require __DIR__ . '/App/Core/bootstrap.php')
    ->handle(Request::capture());
