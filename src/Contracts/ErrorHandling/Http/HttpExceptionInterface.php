<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\ErrorHandling\Http;

/**
 * Represents an exception that carries an HTTP status code.
 *
 * Implementing this interface allows exceptions to explicitly define the HTTP
 * status code that should be returned in the response.
 *
 * Status codes must be valid integers within the 400–599 range.
 * Implementations must never throw.
 */
interface HttpExceptionInterface
{
    /**
     * Returns the HTTP status code associated with the exception.
     *
     * This value will be used as the response status code.
     * It must be an integer between 400 and 599 (inclusive).
     *
     * @return int The HTTP status code.
     */
    public function getStatusCode(): int;
}
