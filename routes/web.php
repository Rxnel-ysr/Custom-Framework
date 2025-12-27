<?php

use App\Foundation\Database\QueryBuilder;
use App\Foundation\Manager\ClassManager;
use App\Foundation\Http\{RouteRequest, Request, Response, HttpHeaders, StaticRequest};
use App\Foundation\Manager\InstanceManager;
use App\Support\Facades\Route;
use App\Foundation\Model;
use App\Foundation\Reactive\ReactiveHandler;
use App\Foundation\Support\Str;
use App\Foundation\System\{File, Disk};
use App\Http\Controllers\test;
use App\Http\Requests\Api\TestRequest;
use App\Models\Comments;
use App\Models\Post;
use App\Models\Student;
use App\Models\Testable;
use App\Models\User;
use App\Support\Facades\DB;
use App\Support\Facades\DI;

Route::get('/', function () {
   // return dd(Route::dump(), Route::debugPatterns());
   // echo '<pre>';
   // print_r([Route::debugPatterns(), $_SERVER['REQUEST_URI']]);
   // echo '</pre>';
   // return 0;
   return view('index');
})->name('home');

Route::get('/route', function () {
   return dd(['route' => Route::getName(), 'route_list' => Route::routeList(), 'ok' => DI::get('nonce')], get_declared_classes());
   // die;
});

Route::get('/raw', function () {
   // return dd(Route::$name, get_declared_classes());
   // die;
   return view('raw');
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

Route::get('/destroy', function () {
   while (true) {
      echo "hi";
   }
});

Route::get('/env', function () {
   // throw new Exception("ok");
   print_rpre(...$_ENV);
});

Route::get('/download', function (Request $req) {
   // $disk = InstanceManager::getInstance('appDisk');

   return response()->download('');
});

Route::view('/debug', 'debug');

Route::get('/request', function (Request $req) {
   // $disk = InstanceManager::getInstance('appDisk');

   print_rpre($req->snapshot(), $_COOKIE);
});

Route::get('/test', function () {
   // echo '<pre>';
   // debug_print_backtrace();
   // echo '</pre>';
   // echo 'entered';
   // die;
   // function ok(){
   //    ok();
   // }

   return view('test');
});

Route::get('/test/{name:.}', function ($name) {
   return "Your name is $name, only 1 char";
});

Route::get('/test/{name:.*?}', function ($name) {
   $len = mb_strlen($name);
   return "Your name is $name, $len chars";
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
   $time =  (hrtime(true) - START) / 1.0e6 . 'ms';
   return response()->json(['server' => $_SERVER, 'serve-time' => $time], true);
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
Route::get('/query', function (Request $request) {
   return response()->json((new QueryBuilder())->table('posts')->get());
});

Route::middleware('', function () {});

Route::get('/test-middleware', function () {
   return "Hello from end point!";
})->middleware('test');


Route::fallback(function () {
   // return response(404)->json(['message' => 'Not found']);
   // return dd(Route::dump(), Route::debugPatterns());
   return abort(404);
});

// // Route::debugTree();
// dependency()

Route::get('/t1', function () {
   return 't1-get';
});

Route::post('/t1', function () {
   return 't1-post';
})->middleware('test');

Route::get('/ungger', function () {
   $base = User::where('name', null, '!=');

   $user5 = $base->with('comments', 'posts')->find(3);

   $nothing = $base->find(999999);

   $aloks = $base->limit(5)->get()
      ->filter(fn($_) => strlen($_->name) > 3, true);
   // abort(403, 'Action unauthorized', 'Hi, mind going back?');

   return [
      'user5' => $user5,
      'nothing' => $nothing,
      'aloks' => $aloks
   ];
});

Route::get('/req', function (TestRequest $request) {
   $data = $request->validated();

   return 'ok, ' . $data['nama'];
})->middleware('test');
