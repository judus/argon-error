<?php

declare(strict_types=1);

namespace Tests\Unit;

use LogicException;
use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Error\ErrorHandler;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Tests\Support\ResponseStub;

final class ErrorHandlerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testHandleReturnsDispatcherResponse(): void
    {
        $dispatcher = $this->createMock(ExceptionDispatcherInterface::class);
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = new RuntimeException('Boom');
        $response = new ResponseStub(418);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($exception, $request)
            ->willReturn($response);
        $formatter->expects($this->never())
            ->method('format');

        $handler = new ErrorHandler($dispatcher, $formatter);

        self::assertSame($response, $handler->handle($exception, $request));
    }

    /**
     * @throws Exception
     */
    public function testHandleFormatsDispatcherFailure(): void
    {
        $dispatcher = $this->createMock(ExceptionDispatcherInterface::class);
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $original = new RuntimeException('Original');
        $fallback = new LogicException('Dispatcher failed');
        $response = new ResponseStub(500);

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($original, $request)
            ->willThrowException($fallback);
        $formatter->expects($this->once())
            ->method('format')
            ->with($fallback, $request)
            ->willReturn($response);

        $handler = new ErrorHandler($dispatcher, $formatter);

        self::assertSame($response, $handler->handle($original, $request));
    }
}
