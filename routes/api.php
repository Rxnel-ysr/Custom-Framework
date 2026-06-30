<?php

use App\Support\Facades\Route;

Route::get('/test', function () {
    return ['success' => true];
});
