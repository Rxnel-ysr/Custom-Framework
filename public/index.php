<?php

declare(strict_types=1);
define('START', hrtime(true));

use App\Foundation\Http\{StaticFile, Request};

require __DIR__ . '/../autoload.php';

if (StaticFile::serve(__DIR__ . '/../', $_SERVER['REQUEST_URI'], __DIR__ . '/../storage/cache')) {
    exit;
}

(require __DIR__ . '/../App/Core/bootstrap/bootstrap.php')
    ->handle(Request::capture());
