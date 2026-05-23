<?php

declare(strict_types=1);

namespace Tests\Support;

use Override;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class StreamStub implements StreamInterface
{
    private int $position = 0;

    public function __construct(
        private string $contents = ''
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->contents;
    }

    #[Override]
    public function close(): void
    {
        $this->contents = '';
        $this->position = 0;
    }

    #[Override]
    public function detach()
    {
        $this->close();

        return null;
    }

    #[Override]
    public function getSize(): ?int
    {
        return strlen($this->contents);
    }

    #[Override]
    public function tell(): int
    {
        return $this->position;
    }

    #[Override]
    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }

    #[Override]
    public function isSeekable(): bool
    {
        return true;
    }

    #[Override]
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $target = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->contents) + $offset,
            default => throw new RuntimeException('Invalid seek mode.'),
        };

        if ($target < 0) {
            throw new RuntimeException('Cannot seek before stream start.');
        }

        $this->position = $target;
    }

    #[Override]
    public function rewind(): void
    {
        $this->position = 0;
    }

    #[Override]
    public function isWritable(): bool
    {
        return true;
    }

    #[Override]
    public function write(string $string): int
    {
        $prefix = substr($this->contents, 0, $this->position);
        $suffix = substr($this->contents, $this->position + strlen($string));
        $this->contents = $prefix . $string . $suffix;
        $this->position += strlen($string);

        return strlen($string);
    }

    #[Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[Override]
    public function read(int $length): string
    {
        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    #[Override]
    public function getContents(): string
    {
        $remaining = substr($this->contents, $this->position);
        $this->position = strlen($this->contents);

        return $remaining;
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        if ($key !== null) {
            return null;
        }

        return [];
    }
}
