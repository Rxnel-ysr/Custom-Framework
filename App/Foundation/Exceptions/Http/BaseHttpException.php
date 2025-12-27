<?php

namespace App\Foundation\Exceptions\Http;

use App\Foundation\Exceptions\Framework\HighLevelException;
use Throwable;

/**
 * Http Exception of Not found exception
 */
abstract class BaseHttpException extends HighLevelException
{
    protected string $subMessage;

    public function __construct(
        string $message = "",
        string $subMessage = "",
        int $code = 0,
        Throwable|null $previous = null
    ) {
        $this->subMessage = $subMessage;
        parent::__construct($message, $code, $previous);
    }

    public function getSubMessage(): string
    {
        return $this->subMessage ?? 'An unexpected error occurred';
    }

    abstract static function httpCode(): int;
}
