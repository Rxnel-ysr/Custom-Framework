<?php

use App\Models\User;

Route::get('/test', function () {
    $user = new User();
    $newU = $user->first();
    $newU();
});
