<?php

use App\Foundation\Manager\ClassManager;

require 'App/Foundation/Manager/ClassManager.php';

$cfg = require 'config/autoloader.php';

ClassManager::set(
    __DIR__,
    $cfg['debug'],
    $cfg['auto'],
    [
        'classmap'      => $cfg['classmap'],
        'cache_classmap' => $cfg['cache'],
        'where_to_look_class' => $cfg['where_to_look_class']
    ],
    $cfg['files']
);

ClassManager::initAutoloader($cfg['auto-resolve']);
