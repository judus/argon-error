<?php

declare(strict_types=1);

namespace Tests\Unit;

use Maduser\Argon\Error\Contracts\HttpExceptionInterface;
use Maduser\Argon\Error\Contracts\SafeToDisplayExceptionInterface as SafeException;
use Maduser\Argon\Error\ExceptionFormatter;
use Override;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\ResponseFactoryStub;
use Tests\Support\StreamFactoryStub;

final class ExceptionFormatterTest extends TestCase
{
    public function testJsonResponseUsesRequestAcceptHeaderAndThrowableCode(): void
    {
        $formatter = $this->createFormatter();

        $response = $formatter->format(
            new RuntimeException('Missing route', 404),
            $this->createRequest('application/json')
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(RuntimeException::class, $response->getHeaderLine('X-Exception-Class'));
        self::assertJsonStringEqualsJsonString(
            '{"error":"Unhandled Exception","message":"Missing route","class":"RuntimeException"}',
            (string) $response->getBody()
        );
    }

    public function testTextResponseHidesTraceByDefault(): void
    {
        $formatter = $this->createFormatter();
        $exception = $this->createTracedException();

        $response = $formatter->format($exception, $this->createRequest('text/plain'));

        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Unhandled Exception: RuntimeException', $body);
        self::assertStringContainsString('Trace hidden', $body);
        self::assertStringNotContainsString('#0', $body);
    }

    public function testTextResponseShowsTraceInDebugMode(): void
    {
        $formatter = $this->createFormatter(debug: true);
        $exception = $this->createTracedException();

        $response = $formatter->format($exception, $this->createRequest('text/plain'));

        self::assertStringContainsString('#0', (string) $response->getBody());
    }

    public function testTextResponseShowsTraceForSafeToDisplayExceptions(): void
    {
        $formatter = $this->createFormatter();
        $exception = new class ('Display me safely') extends RuntimeException implements SafeException {
            #[Override]
            public function isSafeToDisplay(): bool
            {
                return true;
            }
        };

        $response = $formatter->format($exception, $this->createRequest('text/plain'));

        self::assertStringContainsString('#0', (string) $response->getBody());
    }

    /**
     * @throws Exception
     */
    public function testInvalidHttpExceptionStatusFallsBackToServerErrorAndLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $formatter = $this->createFormatter(logger: $logger);
        $exception = new class ('Invalid status') extends RuntimeException implements HttpExceptionInterface {
            #[Override]
            public function getStatusCode(): int
            {
                return 700;
            }
        };

        $logger->expects($this->once())
            ->method('warning')
            ->with('HttpExceptionInterface returned invalid status code', self::callback(
                static fn(array $context): bool => $context['status'] === 700
                    && $context['exception'] === $exception::class
            ));

        $response = $formatter->format($exception, $this->createRequest('text/plain'));

        self::assertSame(500, $response->getStatusCode());
    }

    public function testInvalidJsonPayloadFallsBackToTextResponse(): void
    {
        $formatter = $this->createFormatter();
        $exception = new RuntimeException("\xB1\x31");

        $response = $formatter->format($exception, $this->createRequest('application/json'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('JsonException', (string) $response->getBody());
    }

    private function createFormatter(?LoggerInterface $logger = null, bool $debug = false): ExceptionFormatter
    {
        return new ExceptionFormatter(
            new ResponseFactoryStub(),
            new StreamFactoryStub(),
            $logger,
            $debug
        );
    }

    /**
     * @throws Exception
     */
    private function createRequest(string $accept): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Accept')
            ->willReturn($accept);

        return $request;
    }

    private function createTracedException(): RuntimeException
    {
        try {
            throw new RuntimeException('Trace hidden');
        } catch (RuntimeException $exception) {
            return $exception;
        }
    }
}
