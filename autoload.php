<?php

use App\Foundation\Manager\Autoloader;

require_once __DIR__ . '/App/Foundation/Manager/Autoloader.php';

$cfg = require __DIR__ . '/config/autoloader.php';

$opt = (
    ($cfg['debug']           ?? false ? Autoloader::DEBUG              : 0) |
    ($cfg['auto_resolve']    ?? false ? Autoloader::AUTO_RESOLVE       : 0) |
    ($cfg['auto']            ?? false ? Autoloader::AUTO_INIT          : 0) |
    ($cfg['check_filemtime'] ?? false ? Autoloader::CHECK_FILEMTIME    : 0) |
    ($cfg['read_only']       ?? false ? Autoloader::READ_ONLY          : 0) |
    ($cfg['resolution']['dep'] ?? false ? Autoloader::DEP_RESOLUTION  : 0) |
    ($cfg['resolution']['boot']       ?? false ? Autoloader::BOOT_RESOLUTION : 0)
);

Autoloader::setup(
    __DIR__,
    [
        'classmap'      => $cfg['classmap'],
        'cache_classmap' => $cfg['cache'],
        'where_to_look_class' => $cfg['where_to_look_class'],
        'system_scan' => $cfg['system_scan'],
        'psr-4' => $cfg['psr-4'],
        'except' => $cfg['except']
    ],
    $cfg['files'],
    $opt,
);

Autoloader::registerAttributeAliases([
    'Dep' => App\Foundation\Manager\Dep::class,
    'Boot' => App\Foundation\Manager\Boot::class,
]);

Autoloader::registerAutoloader();
