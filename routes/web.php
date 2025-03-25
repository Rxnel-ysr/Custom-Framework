<?php

use App\Utils\Http\Request;
use App\Utils\Manager\ClassManager;

Route::get('/testting', function (Request $request) {
    echo '<pre>' . join("\n", ClassManager::getLoadedClass()) . '</pre>';

    Test::addStaticMethod('hello',function(){
        echo "hello";
    });

    Test::hello();
});
