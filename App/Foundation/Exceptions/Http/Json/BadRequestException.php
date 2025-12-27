<?php

namespace App\Foundation\Exceptions\Http\Json;

use App\Foundation\Exceptions\Http\Json\JsonBaseHttpException;

/**
 * Http Exception of Not found exception
 */
class BadRequestException extends JsonBaseHttpException
{
    #[\Override]
    public static function httpCode(): int
    {
        return 400;
    }
}
