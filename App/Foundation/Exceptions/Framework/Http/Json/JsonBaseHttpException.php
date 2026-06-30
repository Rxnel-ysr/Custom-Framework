<?php

namespace App\Foundation\Exceptions\Framework\Http\Json;

use App\Foundation\Exceptions\Framework\Http\BaseHttpException;
use App\Foundation\Http\Response;
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
     * @return Response
     */
    public function handle(): Response
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

    public static function httpCode(): int
    {
        return 500;
    }
}
