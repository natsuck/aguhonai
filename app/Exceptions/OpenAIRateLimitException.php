<?php

namespace App\Exceptions;

use Throwable;

class OpenAIRateLimitException extends OpenAIRequestException
{
    public function __construct(
        string $message,
        public readonly int $retryAfter = 60,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
