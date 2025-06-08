<?php

use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Http\{RouteRequest, Request, Response, HttpHeaders, Route};
use App\Foundation\Manager\InstanceManager;
use App\Foundation\Model;
use App\Foundation\System\{File, Disk};
use App\Http\Controllers\test;
use App\Models\User;
use App\Support\Facades\DB;

use function App\Http\Middlewares\auk;

Route::get('/', function () {
   // return dd(Route::dump(), Route::debugPatterns());
   // echo '<pre>';
   // print_r([Route::debugPatterns(), $_SERVER['REQUEST_URI']]);
   // echo '</pre>';
   // return 0;
   return view('index');
});

Route::get('/route', function () {
   return dd(Route::$name, get_declared_classes());
   // die;
});

// Route::get('/testting', function () {

//    // safe(fn() => throw new Exception('Oops!'), [], $result, true, true, function (Throwable $e, array $debug) {
//    //    echo "Auto-injected exception: " . $e->getMessage() . "\n<pre>";
//    //    var_export($debug);
//    //    echo '</pre>';
//    // }, false, false, true);
//    throw new Model();
// });

// Route::get('/raw', function () {
//    return view('raw');
// })->name('raw');

// Route::get('/rx-text', function(Request $req){
//    $message = $req->query('message','Hi');
//    return view('test',compact('message'));
// });

// Route::get('/file', fn() => view('file'));
// Route::post('/file', function (Request $req, Response $res) {

//    // dd($req->file('someFile'));
//    $file = new File($req->file('someFile')['tmp_name']);
//    $file = $file->read();
//    $disk = InstanceManager::getInstance('appDisk');

//    $disk->write('result.txt', $file);
//    $res->serve($disk->path('result.txt'), 'image/jpeg');
// })->name('fileEnd');

// Route::get('/destroy', function () {
//    while (true) {
//       echo "hi";
//    }
// });

Route::get('/download', function () {
   $disk = InstanceManager::getInstance('appDisk');

   return response()->download($disk->path('Design-A3.pdf'));
});

Route::get('/test/{name:\d}', function ($name) {
   return "Your name is $name only one";
});

Route::get('/test/{name:\d+}', function ($name) {
   return "Your name is $name, and many";
});

// Route::group(['prefix' => '/aya'], function () {
//    Route::get('/1', fn() => "Aya 1");
//    Route::get('/2', fn() => "Aya 2");
//    Route::get('/3', fn() => "Aya 3");
// });

// Route::get('/user', function () {
//    $disk = InstanceManager::getInstance('appDisk');
//    response()->serve($disk->path('public/result.txt'));
//    // return dd(Utils::getUserInfo());
// });

// Route::get('/serve-file', function (Request $req) {
//    $disk = InstanceManager::getInstance('appDisk');
//    response()->serve($disk->path($req->query('file')));
// });

Route::get('/up', function () {
   echo (hrtime(true) - START) / 1.0e6 . 'ms<br>';
});

// // Route::get('/test', [test::class, 'test']);
// Route::resource('/test', test::class);

// Route::get('/getMethod', fn() => 'get');
// Route::put('/putMethod', fn() => 'put');
// Route::post('/postMethod', fn() => 'post');
// Route::delete('/deleteMethod', fn() => 'delete');

// Route::get('/debug',function(){
//    $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
//    echo '<pre>';
//    var_dump($debug,http_response_code());
//    echo '</pre>';
// });

Route::fallback(function () {
   // response(404)->json(['message' => 'Its weird']);
   return dd(Route::dump(), Route::debugPatterns());
});

// // Route::debugTree();
