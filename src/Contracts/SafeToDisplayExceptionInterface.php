<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Contracts;

use Throwable;

interface SafeToDisplayExceptionInterface extends Throwable
{
    /**
     * Indicates if the exception can be safely shown to the client.
     *
     * @return bool
     */
    public function isSafeToDisplay(): bool;
}
