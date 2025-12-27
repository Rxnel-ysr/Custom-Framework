<?php

namespace App\Foundation\Exceptions\Http\Json;

use App\Foundation\Exceptions\Http\BaseHttpException;
use Closure;
use Throwable;

/**
 * Http Exception of Not found exception
 */
abstract class JsonBaseHttpException extends BaseHttpException
{
    protected ?array $format = null;

    public function __construct(
        string $message = "",
        string $subMessage = "",
        ?array $format = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->format = $format;
        parent::__construct($message, $subMessage, $code, $previous);
    }

    final public function format(array $format)
    {
        $this->format = $format;
    }

    /**
     * Handle exception as Json Response
     *
     * @return null
     */
    final public function handle(): null
    {
        return response()->json(
            $this->format ?? [
                'message' => $this->getMessage(),
                'detail'  => $this->getSubMessage(),
                'code'    => $this->httpCode()
            ],
            $this->httpCode(),
            ['Content-Type' => 'application/problem+json'],
        );
    }

}
