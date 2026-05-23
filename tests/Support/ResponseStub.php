<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class ResponseStub implements ResponseInterface
{
    /**
     * @var array<string, array{name: string, values: list<string>}>
     */
    private array $headers = [];

    private StreamInterface $body;

    public function __construct(
        private int $statusCode = 200,
        private string $reasonPhrase = ''
    ) {
        $this->body = new StreamStub();
    }

    #[Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[Override]
    public function withProtocolVersion(string $version): ResponseInterface
    {
        return clone $this;
    }

    #[Override]
    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->headers as $header) {
            $headers[$header['name']] = $header['values'];
        }

        return $headers;
    }

    #[Override]
    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->headers);
    }

    #[Override]
    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)]['values'] ?? [];
    }

    #[Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    #[Override]
    public function withHeader(string $name, $value): ResponseInterface
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = [
            'name' => $name,
            'values' => $this->normalizeHeaderValue($value),
        ];

        return $clone;
    }

    #[Override]
    public function withAddedHeader(string $name, $value): ResponseInterface
    {
        $clone = clone $this;
        $lowerName = strtolower($name);
        $values = $clone->headers[$lowerName]['values'] ?? [];
        $clone->headers[$lowerName] = [
            'name' => $name,
            'values' => [...$values, ...$this->normalizeHeaderValue($value)],
        ];

        return $clone;
    }

    #[Override]
    public function withoutHeader(string $name): ResponseInterface
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);

        return $clone;
    }

    #[Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[Override]
    public function withBody(StreamInterface $body): ResponseInterface
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    #[Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    #[Override]
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('Invalid HTTP status code.');
        }

        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    #[Override]
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * @return list<string>
     */
    private function normalizeHeaderValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(static fn(mixed $item): string => (string) $item, array_values($value));
        }

        return [(string) $value];
    }
}
