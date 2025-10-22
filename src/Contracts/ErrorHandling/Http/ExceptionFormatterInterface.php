<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\ErrorHandling\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Defines how exceptions are formatted into PSR-7 responses.
 *
 * Implementations are responsible for converting exceptions into a client-facing response,
 * potentially using request data (e.g., Accept headers) for content negotiation.
 *
 * Implementations must never throw.
 */
interface ExceptionFormatterInterface
{
    /**
     * Formats a Throwable into a PSR-7 response.
     *
     * If the request is available, it may be used to determine content type (e.g., JSON vs plain text).
     * This method must never throw an exception under any circumstance.
     *
     * @param Throwable $e The exception to format.
     * @param ServerRequestInterface $request The current request.
     * @return ResponseInterface The rendered response representing the error.
     */
    public function format(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
