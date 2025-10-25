<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Contracts;

interface ErrorHandlerRegistrarInterface
{
    public function register(ExceptionDispatcherInterface $dispatcher): void;
}
