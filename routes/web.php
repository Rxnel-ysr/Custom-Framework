<?php

use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Http\Route;
use App\Http\Controllers\test;
use App\Support\Facades\Request;

Route::get('/', function () {
   return view('index');
});

// Route::get('/testting', function () {

//    safe(fn() => throw new Exception('Oops!'), [], $result, true, true, function (Throwable $e, array $debug) {
//       echo "Auto-injected exception: " . $e->getMessage() . "\n<pre>";
//       var_export($debug);
//       echo '</pre>';
//    }, false, false, true);
// });

Route::get('/raw', function () {
   return view('raw');
});

Route::get('/destroy', function () {
   while (true) {
      echo "hi";
   }
});

Route::get('/user',function(Request $req){
   return Utils::getUserInfo();
});

Route::get('/list',function(){
return response()->json(Route::routeList());
});

Route::delete('/form-endpoint',function(){
    print_r(Request::all());
});

Route::get('/test', [test::class, 'test']);

Route::fallback(fn()=>response(404)->json(['msg'=>"Not found dude"]));
