<?php

use App\Foundation\Http\Request;
use App\Foundation\Http\Response;
use App\Support\Facades\Route;

Route::get('/', function () {
   return view('index');
});

Route::get('/robot.txt', function (Response $response) {
   
});

Route::get('/test', function(Request $request){

   return $request->getHeaders();
});

