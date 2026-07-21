<?php

namespace App\Http\Middlewares;

use App\Foundation\Http\Middleware;
use App\Foundation\Http\Request;
use Closure;

class CSRF extends Middleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (isset($_REQUEST['csrf_']) || isset($_REQUEST['csrf_key'])) {
            App\Foundation\Guard\CSRF::validateCSRF();
        }
        return $next($request);
    }
}
