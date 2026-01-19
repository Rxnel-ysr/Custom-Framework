<?php

use App\Foundation\Exceptions\Http\Json\NotFoundException;
use App\Foundation\Http\Request;
use App\Foundation\Http\Response;
use App\Foundation\Manager\InstanceManager;
use App\Foundation\System\Disk;
use App\Http\Controllers\Test;
use App\Support\Facades\DI;
use App\Support\Facades\Route;

Route::get('/haha', function () {
   
})->middleware('cors');

Route::get('/test', [Test::class, 'index'])->middleware('test');
Route::get('/download/{filename}', function($filename){
    /**
     * @var Disk $disk
     */
    $disk = InstanceManager::getInstance('appDisk');

    return response()->downloadLimit($disk->path("/media/{$filename}"), 'Gorengan.pdf', 60);
    // return 'Haha';
});

Route::get('/route', function () {
    return dd(['route' => Route::getName(), 'route_list' => Route::routeList(), 'ok' => DI::get('nonce')], get_declared_classes());
    // die;
});

Route::get('/success', fn() => response()->json(['success' => 'true']));

Route::post('/test-manek', function(Request $request){
    print_rpred($request->name, getallheaders(), $request->bearerToken());
});

Route::post('/all', fn(Request $request) => $request->all());