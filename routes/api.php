<?php

Route::get('/api/hi',function(){
    return response()->json([
        'message' => 'sausage!'
    ]);
});