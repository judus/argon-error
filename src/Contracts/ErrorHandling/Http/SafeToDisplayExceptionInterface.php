<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\ErrorHandling\Http;

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
