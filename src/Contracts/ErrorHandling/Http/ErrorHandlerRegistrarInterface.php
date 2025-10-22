<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\ErrorHandling\Http;

interface ErrorHandlerRegistrarInterface
{
    public function register(ExceptionDispatcherInterface $dispatcher): void;
}
