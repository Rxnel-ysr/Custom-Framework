<?php

namespace App\Foundation\Providers;

use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Foundation\Reactive\ReactiveHandler;
use App\Support\Facades\Rx;

class AppServiceProvider
{

    public static function register(): void
    {
        // Register
        Rx::register('say', function (string $message): void {
            echo $message;
        });

    }

    public static function boot(): void
    {
        // Boot 
    }
}
