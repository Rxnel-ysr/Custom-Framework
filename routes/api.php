<?php

use App\Foundation\Exceptions\Http\Json\NotFoundException;
use App\Foundation\Http\Response;
use App\Http\Controllers\Test;
use App\Support\Facades\Route;

Route::get('/haha', function (Response $res) {
    throw new NotFoundException("Not found", "gk onok cik");

    return $res->json([
        'message' => 'sausage!'
    ]);
});