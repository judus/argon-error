<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ExceptionPolicyRegistryInterface
{
    /**
     * @param class-string<Throwable>|list<class-string<Throwable>> $exceptionClass
     * @param callable(Throwable, ServerRequestInterface): void $reporter
     */
    public function report(string|array $exceptionClass, callable $reporter): void;

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>> $exceptionClass
     * @param callable(Throwable, ServerRequestInterface): ?ResponseInterface $renderer
     */
    public function render(string|array $exceptionClass, callable $renderer): void;
}
