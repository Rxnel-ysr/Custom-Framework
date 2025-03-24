<?php

use App\Models\User;
use App\Utils\Http\Response;
use App\Utils\Manager\ClassManager;

Route::get('/testting/{test}/{yuu}', function (Response $response, bool $test, int $yuu) {
    echo '<pre>' . join("\n", ClassManager::getLoadedClass()) . '</pre>';
    $user = new User();

    // Test::testError();
    var_dump($test, $yuu);

    // dd(...$user->with(['has.many.posts'])->get());

});
