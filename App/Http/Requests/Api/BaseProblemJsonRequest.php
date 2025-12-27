<?php

namespace App\Http\Requests\Api;

use App\Foundation\Exceptions\Http\Json\BadRequestException;
use App\Foundation\Http\FormDataRequest;

abstract class BaseProblemJsonRequest extends FormDataRequest
{
    protected function handleValidationFailure(): void
    {
        throw new BadRequestException(
            "Validation not satisfied",
            "",
            [
                "type" => 'urn:problem:validation:failed',
                "title" => "Validation failed",
                "status" => BadRequestException::httpCode(),
                "detail" => "Unable to handle request due validation failed",
                "instance" => $this->fullUri(),
                "error" => $this->ungroupedErrors(),
                ...$this->additionalClues()
            ]
        );
    }

    /**
     * Add additional extension to problem json response
     * 
     * @return array
     */
    protected function additionalClues(): array
    {
        return [];
    }
}
