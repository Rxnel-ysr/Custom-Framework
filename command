#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Foundation\CLI\Argv;

if (PHP_SAPI !== 'cli') {
    return die('Must run on CLI');
}
require_once 'autoload.php';
require_once __DIR__ . '/routes/console.php';

$status = (require __DIR__ . '/App/Core/bootstrap/bootstrap.php')
    ->handleCommand(new Argv());

exit($status);

