<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\ErrorHandling\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * @psalm-type ExceptionHandler = callable(Throwable, ServerRequestInterface): ?ResponseInterface
 */
interface ExceptionDispatcherInterface
{
    /**
     * @param class-string<Throwable>|list<class-string<Throwable>> $exceptionClass
     * @param callable(Throwable, ServerRequestInterface): ?ResponseInterface $handler
     */
    public function register(string|array $exceptionClass, callable $handler): void;

    public function dispatch(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
