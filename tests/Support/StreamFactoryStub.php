<?php

declare(strict_types=1);

namespace Tests\Support;

use Override;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class StreamFactoryStub implements StreamFactoryInterface
{
    #[Override]
    public function createStream(string $content = ''): StreamInterface
    {
        return new StreamStub($content);
    }

    #[Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new StreamStub((string) file_get_contents($filename));
    }

    #[Override]
    public function createStreamFromResource($resource): StreamInterface
    {
        $contents = stream_get_contents($resource);

        return new StreamStub($contents === false ? '' : $contents);
    }
}
