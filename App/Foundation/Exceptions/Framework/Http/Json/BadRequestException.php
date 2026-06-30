<?php

namespace App\Foundation\Exceptions\Framework\Http\Json;

use App\Foundation\Exceptions\Framework\Http\Json\JsonBaseHttpException;

/**
 * Http Exception of Not found exception
 */
class BadRequestException extends JsonBaseHttpException
{
    public static function httpCode(): int
    {
        return 400;
    }
}
