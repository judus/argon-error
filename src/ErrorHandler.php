<?php

declare(strict_types=1);

namespace Maduser\Argon\Error;

use ErrorException;
use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Support\Contracts\ErrorHandlerInterface;
use Maduser\Argon\Support\Contracts\ResponseEmitterInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ErrorHandler implements ErrorHandlerInterface
{
    private bool $registered = false;

    /** @var callable|null */
    private $previousErrorHandler = null;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    private ?ServerRequestInterface $bootstrapRequest;
    private ?ServerRequestInterface $currentRequest = null;
    private bool $emittingFallback = false;

    public function __construct(
        private readonly ExceptionDispatcherInterface $dispatcher,
        private readonly ExceptionFormatterInterface $formatter,
        private readonly ?LoggerInterface $logger = null,
        ?ServerRequestInterface $request = null,
        private readonly ?ResponseEmitterInterface $emitter = null,
    ) {
        $this->bootstrapRequest = $request;
    }

    #[Override]
    public function register(): void
    {
        if ($this->registered) {
            $this->logger?->info('Exception handler already registered, skipping...', [
                'class' => get_class($this),
            ]);

            return;
        }

        $this->logger?->info('Registering exception handler', [
            'class' => get_class($this),
        ]);

        $this->registered = true;

        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'shutdownFunction']);
    }

    #[Override]
    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $this->currentRequest = $request;

        try {
            /**
             * The HTTP kernel calls this after capturing a Throwable during the request
             * pipeline. At this point we have a valid request instance, so we can safely
             * delegate to the dispatcher/formatter stack and return a response that the
             * kernel will emit to the client.
             */
            return $this->dispatchException($e, $request);
        } catch (Throwable $fallback) {
            return $this->handleFallbackException($e, $fallback, $request);
        }
    }

    private function dispatchException(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatcher->dispatch($e, $request);
    }

    private function handleFallbackException(
        Throwable $original,
        Throwable $fallback,
        ServerRequestInterface $request
    ): ResponseInterface {
        $this->logger?->critical('Dispatcher failure during exception handling', [
            'original_exception' => $original,
            'fallback_exception' => $fallback,
        ]);

        return $this->formatter->format($fallback, $request);
    }

    /**
     * Handles PHP errors and converts them into exceptions.
     *
     * This callback is registered via set_error_handler() as a last-resort logging
     * mechanism. We intentionally swallow the converted exception here to avoid
     * interrupting the normal request lifecycle—runtime exceptions are handled through
     * handle(), which the kernel calls with full request context.
     */
    public function handleError(
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        try {
            throw new ErrorException($message, 0, $severity, $file, $line);
        } catch (Throwable $e) {
            $this->logger?->critical('Error converted to Exception', ['exception' => $e]);
            if ($this->previousErrorHandler !== null) {
                ($this->previousErrorHandler)($severity, $message, $file, $line);
            }
        }

        return true;
    }

    public function handleException(Throwable $e): void
    {
        $this->logger?->critical('Unhandled throwable', ['exception' => $e]);

        $request = $this->currentRequest ?? $this->bootstrapRequest;

        if (!$request instanceof ServerRequestInterface) {
            $this->delegateToPreviousHandler($e);
            return;
        }

        try {
            $response = $this->dispatchException($e, $request);
            $this->emitResponse($response);
        } catch (Throwable $fallback) {
            $this->logger?->critical('Exception handler failed while dispatching', [
                'original_exception' => $e,
                'fallback_exception' => $fallback,
            ]);

            try {
                $response = $this->formatter->format($fallback, $request);
                $this->emitResponse($response);
            } catch (Throwable $formatterFailure) {
                $this->logger?->critical('Formatter failed during exception handling', [
                    'exception' => $formatterFailure,
                ]);
                $this->delegateToPreviousHandler($e);
            }
        }
    }

    /**
     * Handles shutdown errors.
     *
     * @param array{type: int, message: string, file: string, line: int}|null $error
     *        Optional error array, typically provided by `error_get_last()`.
     */
    private function shutdownFunction(?array $error = null): void
    {
        $error ??= error_get_last();
        if ($this->isFatalError($error)) {
            $throwable = new ErrorException(
                $error['message'] ?? 'Fatal error',
                0,
                $error['type'] ?? E_ERROR,
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0
            );
            $this->logger?->critical('Fatal shutdown error', ['exception' => $throwable]);
        }
    }

    private function isFatalError(?array $error): bool
    {
        return $error !== null && in_array($error['type'], [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
            ], true);
    }

    private function delegateToPreviousHandler(Throwable $e): void
    {
        if ($this->previousExceptionHandler !== null) {
            ($this->previousExceptionHandler)($e);
            return;
        }

        if (!$this->emittingFallback) {
            $this->emittingFallback = true;
            $this->emitFallbackMessage($e);
            $this->emittingFallback = false;
        }
    }

    private function emitResponse(ResponseInterface $response): void
    {
        if ($this->emitter !== null) {
            $this->emitter->emit($response);
        } else {
            $this->emitViaSapi($response);
        }

        exit($response->getStatusCode() >= 500 ? 1 : 0);
    }

    private function emitViaSapi(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }

        $this->emitBody($response->getBody());
    }

    private function emitBody(StreamInterface $body): void
    {
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(8192);
        }
    }

    private function emitFallbackMessage(Throwable $e): void
    {
        http_response_code(500);

        echo sprintf(
            "Fatal error: %s in %s:%d\n\n%s\n",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        exit(1);
    }
}
