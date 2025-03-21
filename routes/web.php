<?php

use App\Http\Controllers\test;

Route::get('/test', [test::class,'test']);

// // $start = hrtime(true);
// // Route::dispatch($requestUri);
// // $end = hrtime(true);

// echo '<br />Execution time: ' . ($end - $start) / 1e6 . ' ms';

// $sum = 0;
// $iterations = 1000;

// for ($j = 0; $j < $iterations; $j++) {
//     $start = hrtime(true);
//     Route::dispatch($requestUri);
//     $end = hrtime(true);
//     $sum += ($end - $start);
// }

// echo '<br />Average Execution time: ' . ($sum / $iterations) / 1e6 . ' ms';
