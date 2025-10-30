<?php

use App\Foundation\Manager\Autoloader;

require 'App/Foundation/Manager/Autoloader.php';

$cfg = require 'config/autoloader.php';

$opt = (
    ($cfg['debug']           ?? false ? AutoLoader::DEBUG              : 0) |
    ($cfg['auto-resolve']    ?? false ? AutoLoader::AUTO_RESOLVE       : 0) |
    ($cfg['auto']            ?? false ? AutoLoader::AUTO_INIT          : 0) |
    ($cfg['check_filemtime'] ?? false ? AutoLoader::CHECK_FILEMTIME    : 0) |
    ($cfg['read_only']       ?? false ? AutoLoader::READ_ONLY          : 0)
);

// $start = hrtime(true);
Autoloader::setup(
    __DIR__,
    [
        'classmap'      => $cfg['classmap'],
        'cache_classmap' => $cfg['cache'],
        'where_to_look_class' => $cfg['where_to_look_class'],
        'psr-4' => $cfg['psr-4'],
        'except' => $cfg['except']
    ],
    $cfg['files'],
    $opt,
);
// $rs = (hrtime(true) - $start) / 1.0e6 . "ms\n";
// echo $rs;

Autoloader::registerAutoloader();
