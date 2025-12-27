<?php

namespace App\Foundation\Exceptions\Http;

/**
 * Http Exception of Unauthorized action
 */
class UnauthorizedException extends BaseHttpException
{
    #[\Override]
    public static function httpCode(): int
    {
        return 401;
    }
    
}
