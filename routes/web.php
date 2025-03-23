<?php

use App\Models\User;
use App\Utils\Manager\ClassManager;

Route::get('/testting', function () {
    echo '<pre>'.join("\n",ClassManager::getLoadedClass()).'</pre>';
    $user = new User();

    Test::testError();

    dd(...$user->with(['has.many.posts'])->get());

});
