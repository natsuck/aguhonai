<?php

namespace App\Exceptions;

use Throwable;

class OpenAIQuotaExceededException extends OpenAIRequestException
{
    public function __construct(
        string $message = 'The OpenAI API quota has been exhausted.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
