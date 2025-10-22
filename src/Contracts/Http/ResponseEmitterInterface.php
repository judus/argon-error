<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\Http;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
    /**
     * Emits a PSR-7 Response to the client.
     *
     * @param ResponseInterface $response
     * @return void
     */
    public function emit(ResponseInterface $response): void;
}
