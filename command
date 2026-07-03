#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    return die('Must run on CLI');
}
require_once 'autoload.php';
require_once __DIR__ . '/routes/console.php';

$status = (require __DIR__ . '/App/Core/bootstrap/bootstrap.php')
    ->handleCommand();

exit($status);
