<?php

use App\Foundation\Http\Response;
use App\Foundation\Http\Route;
use App\Http\Controllers\Test;

Route::get('/hi', function (Response $res) {
    return $res->json([
        'message' => 'sausage!'
    ]);
});

Route::fallback(fn(Response $res) => $res->status(404)->json(['message' => 'Not found']));
