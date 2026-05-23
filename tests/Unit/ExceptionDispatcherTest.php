<?php

declare(strict_types=1);

namespace Tests\Unit;

use LogicException;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Error\ExceptionDispatcher;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\Support\ResponseStub;
use Throwable;

final class ExceptionDispatcherTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testMatchingHandlerResponseIsReturned(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(418);

        $formatter->expects($this->never())
            ->method('format');

        $dispatcher->register(RuntimeException::class, static fn(): ResponseInterface => $expectedResponse);

        self::assertSame(
            $expectedResponse,
            $dispatcher->dispatch(new RuntimeException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testHandlerCanBeRegisteredForMultipleExceptionClasses(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(409);

        $dispatcher->register(
            [RuntimeException::class, LogicException::class],
            static fn(): ResponseInterface => $expectedResponse
        );

        self::assertSame(
            $expectedResponse,
            $dispatcher->dispatch(new LogicException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testDispatcherLogsFailingHandlerAndContinuesToNextHandler(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter, $logger);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(422);

        $logger->expects($this->once())
            ->method('warning')
            ->with('Handler failed', self::callback(
                static fn(array $context): bool => $context['exception'] instanceof LogicException
            ));

        $dispatcher->register(RuntimeException::class, static function (): void {
            throw new LogicException('Handler failed');
        });
        $dispatcher->register(RuntimeException::class, static fn(): ResponseInterface => $expectedResponse);

        self::assertSame(
            $expectedResponse,
            $dispatcher->dispatch(new RuntimeException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testNullHandlerResponseFallsBackToFormatter(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $exception = new RuntimeException('Format me');
        $fallbackResponse = new ResponseStub(500);

        $dispatcher->register(RuntimeException::class, static fn(): null => null);

        $formatter->expects($this->once())
            ->method('format')
            ->with($exception, $request)
            ->willReturn($fallbackResponse);

        self::assertSame($fallbackResponse, $dispatcher->dispatch($exception, $request));
    }

    /**
     * @throws Exception
     */
    public function testNoMatchingHandlerFallsBackToFormatter(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $exception = new LogicException('Format me');
        $fallbackResponse = new ResponseStub(500);

        $formatter->expects($this->once())
            ->method('format')
            ->with($exception, $request)
            ->willReturn($fallbackResponse);

        self::assertSame($fallbackResponse, $dispatcher->dispatch($exception, $request));
    }

    /**
     * @throws Exception
     */
    private function createRequest(): ServerRequestInterface
    {
        return $this->createMock(ServerRequestInterface::class);
    }
}
