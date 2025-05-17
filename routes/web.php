<?php

use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Http\Request;
use App\Foundation\Http\Response;
use App\Foundation\Http\Route;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Model;
use App\Foundation\System\Disk;
use App\Foundation\System\File;
use App\Http\Controllers\test;

use function App\Http\Middlewares\auk;

Route::get('/', function () {
   return view('index');
});

Route::get('/testting', function () {

   // safe(fn() => throw new Exception('Oops!'), [], $result, true, true, function (Throwable $e, array $debug) {
   //    echo "Auto-injected exception: " . $e->getMessage() . "\n<pre>";
   //    var_export($debug);
   //    echo '</pre>';
   // }, false, false, true);
   throw new Model();
});

Route::get('/raw', function () {
   return view('raw');
})->name('raw');

Route::get('/file', fn() => view('file'));
Route::post('/file', function (Request $req, Response $res) {

   // dd($req->file('someFile'));
   $file = new File($req->file('someFile')['tmp_name']);
   $file = $file->read();
   $disk = InstanceManager::getInstance('appDisk');

   $disk->write('result.txt', $file);
   $res->serve($disk->path('result.txt'), 'image/jpeg');
})->name('fileEnd');

Route::get('/destroy', function () {
   while (true) {
      echo "hi";
   }
});

Route::group(['prefix' => '/aya', 'middleware' => ['auk' => fn() => auk()]], function () {
   Route::get('/1', fn() => "Aya 1");
   Route::get('/2', fn() => "Aya 2");
   Route::get('/3', fn() => "Aya 3");
});

Route::get('/list', function () {
   // response()->json(Route::routeList());
   dd(Route::routeList());
});

// Route::get('/test', [test::class, 'test']);
Route::resource('/test', test::class);

Route::get('/getMethod', fn() => 'get');
Route::put('/putMethod', fn() => 'put');
Route::post('/postMethod', fn() => 'post');
Route::delete('/deleteMethod', fn() => 'delete');

Route::fallback(function () {
   // response(404)->json(['message' => 'Its weird']);
   return    dd(Route::routeList());
});

// Route::debugTree();
