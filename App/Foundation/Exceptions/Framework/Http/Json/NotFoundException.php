<?php

namespace App\Foundation\Exceptions\Framework\Http\Json;

use App\Foundation\Exceptions\Framework\Http\Json\JsonBaseHttpException;


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
