<?php

declare(strict_types=1);

namespace Tests\Unit;

use LogicException;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Error\ExceptionDispatcher;
use InvalidArgumentException;
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
    public function testMatchingRendererResponseIsReturned(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(418);

        $formatter->expects($this->never())
            ->method('format');

        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $expectedResponse);

        self::assertSame(
            $expectedResponse,
            $dispatcher->dispatch(new RuntimeException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testRendererCanBeRegisteredForMultipleExceptionClasses(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(409);

        $dispatcher->render(
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
    public function testFailingRendererIsLoggedAndNextRendererCanHandleException(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter, $logger);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(422);

        $logger->expects($this->once())
            ->method('warning')
            ->with('Exception renderer failed', self::callback(
                static fn(array $context): bool => $context['renderer_exception'] instanceof LogicException
            ));

        $dispatcher->render(RuntimeException::class, static function (): void {
            throw new LogicException('Handler failed');
        });
        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $expectedResponse);

        self::assertSame(
            $expectedResponse,
            $dispatcher->dispatch(new RuntimeException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testNullRendererResponseFallsBackToFormatter(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $exception = new RuntimeException('Format me');
        $fallbackResponse = new ResponseStub(500);

        $dispatcher->render(RuntimeException::class, static fn(): null => null);

        $formatter->expects($this->once())
            ->method('format')
            ->with($exception, $request)
            ->willReturn($fallbackResponse);

        self::assertSame($fallbackResponse, $dispatcher->dispatch($exception, $request));
    }

    /**
     * @throws Exception
     */
    public function testNullRendererResponseContinuesToNextRenderer(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $expectedResponse = new ResponseStub(422);

        $formatter->expects($this->never())
            ->method('format');

        $dispatcher->render(RuntimeException::class, static fn(): null => null);
        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $expectedResponse);

        self::assertSame($expectedResponse, $dispatcher->dispatch(new RuntimeException('Handled'), $request));
    }

    /**
     * @throws Exception
     */
    public function testMostSpecificRendererWinsBeforeRegistrationOrder(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $parentResponse = new ResponseStub(500);
        $specificResponse = new ResponseStub(409);

        $dispatcher->render(Throwable::class, static fn(): ResponseInterface => $parentResponse);
        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $specificResponse);

        self::assertSame(
            $specificResponse,
            $dispatcher->dispatch(new RuntimeException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testRegistrationOrderIsKeptWithinSameSpecificity(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $firstResponse = new ResponseStub(409);
        $secondResponse = new ResponseStub(410);

        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $firstResponse);
        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $secondResponse);

        self::assertSame(
            $firstResponse,
            $dispatcher->dispatch(new RuntimeException('Handled'), $request)
        );
    }

    /**
     * @throws Exception
     */
    public function testAllMatchingReportersRunBeforeRendering(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);
        $request = $this->createRequest();
        $response = new ResponseStub(204);
        $events = [];

        $dispatcher->report(Throwable::class, static function () use (&$events): void {
            $events[] = 'all';
        });
        $dispatcher->report(RuntimeException::class, static function () use (&$events): void {
            $events[] = 'specific';
        });
        $dispatcher->render(RuntimeException::class, static function () use (&$events, $response): ResponseInterface {
            $events[] = 'render';
            return $response;
        });

        self::assertSame($response, $dispatcher->dispatch(new RuntimeException('Handled'), $request));
        self::assertSame(['all', 'specific', 'render'], $events);
    }

    /**
     * @throws Exception
     */
    public function testFailingReporterIsLoggedAndRenderingContinues(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter, $logger);
        $request = $this->createRequest();
        $response = new ResponseStub(202);

        $logger->expects($this->once())
            ->method('warning')
            ->with('Exception reporter failed', self::callback(
                static fn(array $context): bool => $context['reporter_exception'] instanceof LogicException
            ));

        $dispatcher->report(RuntimeException::class, static function (): void {
            throw new LogicException('Reporter failed');
        });
        $dispatcher->render(RuntimeException::class, static fn(): ResponseInterface => $response);

        self::assertSame($response, $dispatcher->dispatch(new RuntimeException('Handled'), $request));
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

    public function testExceptionClassMustBeThrowable(): void
    {
        $formatter = $this->createMock(ExceptionFormatterInterface::class);
        $dispatcher = new ExceptionDispatcher($formatter);

        $this->expectException(InvalidArgumentException::class);

        /** @psalm-suppress InvalidArgument */
        $dispatcher->render(self::class, static fn(): null => null);
    }

    /**
     * @throws Exception
     */
    private function createRequest(): ServerRequestInterface
    {
        return $this->createMock(ServerRequestInterface::class);
    }
}
