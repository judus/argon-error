<?php

declare(strict_types=1);

namespace Maduser\Argon\Error;

use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyRegistryInterface;

final readonly class ExceptionPolicyRegistryFactory
{
    public function __invoke(ExceptionDispatcherInterface $dispatcher): ExceptionPolicyRegistryInterface
    {
        assert($dispatcher instanceof ExceptionPolicyRegistryInterface);

        return $dispatcher;
    }
}
