<?php

namespace App\Http\Middlewares;

use App\Foundation\Exceptions\Http\UnauthorizedException;
use App\Foundation\Http\Middleware;
use App\Foundation\Http\Request;
use Closure;
use Exception;

class Test extends Middleware
{

    public function handle(Request $request, Closure $next, string $msg): mixed
    {
        $this->header->set('X-Powered-By', 'o');
        return $next($request);
    }
}
