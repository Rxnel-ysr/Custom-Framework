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
        
        // echo "This has through test middleware<br>";
        // echo "This was message given: {$msg}<br>";
        throw new UnauthorizedException("Unauthorized action", "Nu uh");
        
        return $next($request);
    }
}
