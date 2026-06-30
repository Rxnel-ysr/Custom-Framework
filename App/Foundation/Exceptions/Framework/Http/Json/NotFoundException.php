<?php

namespace App\Foundation\Exceptions\Http\Json;

use App\Foundation\Exceptions\Framework\Http\Json\JsonBaseHttpException;

// require __DIR__ . '/JsonBaseHttpException.php';

/**
 * Http Exception of Not found exception
 */
class NotFoundException extends JsonBaseHttpException
{
    // #[\Override]
    public static function httpCode(): int
    {
        return 404;
    }
}
