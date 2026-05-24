<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ExceptionDispatcherInterface
{
    public function dispatch(Throwable $e, ServerRequestInterface $request): ResponseInterface;
}
