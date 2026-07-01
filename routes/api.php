<?php

use App\Support\Facades\Route;

Route::get('/test', function () {
    return ['success' => true];
});

Route::get('/list', function(){
    return [["name" => "1"], ["name" => "2"], ["name" => "3"]];
});