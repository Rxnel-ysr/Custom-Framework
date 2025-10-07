<?php

declare(strict_types=1);
define('START', hrtime(true));

use App\Foundation\Http\{StaticFile, Request};

require __DIR__ . '/../autoload.php';
$_ = dirname(__DIR__);

if (StaticFile::serve($_, $_SERVER['REQUEST_URI'], $_ . '/storage/cache')) {
    exit;
}

(require __DIR__ . '/../App/Core/bootstrap/bootstrap.php')
    ->handle(Request::capture());
