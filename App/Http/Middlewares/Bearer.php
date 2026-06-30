<?php

namespace App\Http\Middlewares;

use App\Foundation\Exceptions\Http\UnauthorizedException;
use App\Foundation\Http\Middleware;
use App\Foundation\Http\Request;
use Closure;
use Exception;

class Bearer extends Middleware
{

    public function handle(Request $request, Closure $next, string $msg): mixed
    {
        if (!$request->bearer()) {
            return response()->json([
                'message' => 'invalid bearer'
            ]);
        }
        return $next($request);
    }
}
