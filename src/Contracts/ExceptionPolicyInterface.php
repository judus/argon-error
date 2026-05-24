<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Contracts;

interface ExceptionPolicyInterface
{
    public function register(ExceptionPolicyRegistryInterface $exceptions): void;
}
