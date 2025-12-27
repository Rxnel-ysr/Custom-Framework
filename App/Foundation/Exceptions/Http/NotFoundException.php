<?php

namespace App\Foundation\Exceptions\Http;

/**
 * Http Exception of Not found exception
 */
class NotFoundException extends BaseHttpException
{
    #[\Override]
    public static function httpCode(): int
    {
        return 404;
    }
}
