<?php

use App\Foundation\Http\Request;
use App\Foundation\Http\Response;
use App\Support\Facades\Route;

Route::get('/', function () {
   return view('index');
});

Route::get('/robot.txt', function (Response $response) {
   $response->headers->contentType('text/plain');
   return "<h1>Hello</h1>";
});

Route::get('/test', function (Request $request) {

   return "Hello";
});
