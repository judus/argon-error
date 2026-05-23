<?php

declare(strict_types=1);

namespace Tests\Support;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final class ResponseFactoryStub implements ResponseFactoryInterface
{
    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new ResponseStub($code, $reasonPhrase);
    }
}
