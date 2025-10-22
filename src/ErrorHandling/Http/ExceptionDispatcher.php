<?php

declare(strict_types=1);

namespace Maduser\Argon\ErrorHandling\Http;

use Maduser\Argon\Contracts\ErrorHandling\Http\ExceptionDispatcherInterface;
use Maduser\Argon\Contracts\ErrorHandling\Http\ExceptionFormatterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @psalm-type ExceptionHandler = callable(Throwable, ServerRequestInterface): ?ResponseInterface
 */
final class ExceptionDispatcher implements ExceptionDispatcherInterface
{
    /**
     * @var array<class-string<Throwable>, list<callable(Throwable, ServerRequestInterface): ?ResponseInterface>>
     */
    private array $map = [];

    public function __construct(
        private readonly ExceptionFormatterInterface $formatter,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>> $exceptionClass
     * @param callable(Throwable, ServerRequestInterface): ?ResponseInterface $handler
     */
    public function register(string|array $exceptionClass, callable $handler): void
    {
        /** @var list<class-string<Throwable>> $classes */
        $classes = is_array($exceptionClass) ? $exceptionClass : [$exceptionClass];

        foreach ($classes as $class) {
            $this->map[$class][] = $handler;
        }
    }

    public function dispatch(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        foreach ($this->map as $class => $handlers) {
            if ($e instanceof $class) {
                foreach ($handlers as $handler) {
                    try {
                        $response = $handler($e, $request);
                        if ($response instanceof ResponseInterface) {
                            return $response;
                        }
                    } catch (Throwable $handlerException) {
                        $this->logger?->warning('Handler failed', [
                            'handler' => get_debug_type($handler),
                            'exception' => $handlerException,
                        ]);
                    }
                }
            }
        }
        return $this->formatter->format($e, $request);
    }
}
