<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\ErrorHandling\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Handles reporting and rendering of unhandled exceptions.
 *
 * This interface defines how exceptions are logged and transformed into PSR-7 responses.
 * Implementations must be infallible — methods must never throw.
 */
interface ErrorHandlerInterface
{
    public function register(): void;

    /**
     * Handles an exception by logging it and returning a response.
     *
     * This is the main entry point for exception handling.
     *
     * @param Throwable $e The exception to handle.
     * @param ServerRequestInterface $request The current request.
     * @return ResponseInterface A PSR-7 response suitable for the exception.
     */
    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
