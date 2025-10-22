<?php

declare(strict_types=1);

namespace Maduser\Argon\ErrorHandling\Http;

use JsonException;
use Maduser\Argon\Contracts\ErrorHandling\Http\SafeToDisplayExceptionInterface;
use Maduser\Argon\Contracts\ErrorHandling\Http\ExceptionFormatterInterface;
use Maduser\Argon\Contracts\ErrorHandling\Http\HttpExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ExceptionFormatter implements ExceptionFormatterInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private ?LoggerInterface $logger = null,
        private bool $debug = false
    ) {
    }

    private function createResponse(
        Throwable $e,
        string $body,
        string $contentType
    ): ResponseInterface {
        return $this->responseFactory->createResponse($this->getStatusCode($e))
            ->withHeader('Content-Type', $contentType)
            ->withHeader('X-Exception-Class', $e::class)
            ->withBody($this->streamFactory->createStream($body));
    }

    /**
     * Formats an exception into a PSR-7 Response.
     *
     * This method will never throw.
     *
     * @param Throwable $e
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function format(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        return str_contains($request->getHeaderLine('Accept'), 'application/json')
            ? $this->formatJson($e)
            : $this->formatText($e);
    }

    private function formatJson(Throwable $e): ResponseInterface
    {
        try {
            $json = json_encode([
                'error' => 'Unhandled Exception',
                'message' => $e->getMessage(),
                'class' => $e::class,
            ], JSON_THROW_ON_ERROR);

            return $this->createResponse($e, $json, 'application/json; charset=UTF-8');
        } catch (JsonException $jsonException) {
            $this->logger?->error('Failed to JSON encode exception', [
                'original_exception' => $e,
                'json_exception' => $jsonException,
            ]);

            return $this->formatText($jsonException);
        }
    }

    private function formatText(Throwable $e): ResponseInterface
    {
        $shouldShowTrace = $this->debug || ($e instanceof SafeToDisplayExceptionInterface && $e->isSafeToDisplay());

        $message = sprintf(
            "Unhandled Exception: %s\n\n%s",
            $e::class,
            $e->getMessage()
        );

        if ($shouldShowTrace) {
            $message .= "\n\n" . $e->getTraceAsString();
        }

        return $this->createResponse($e, $message, 'text/plain; charset=UTF-8');
    }

    private function getStatusCode(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            if ($status >= 400 && $status <= 599) {
                return $status;
            }

            $this->logger?->warning('HttpExceptionInterface returned invalid status code', [
                'status' => $status,
                'exception' => $e::class,
            ]);
        }

        return $this->guessCode($e);
    }

    private function guessCode(Throwable $e): int
    {
        $code = $e->getCode();

        return is_int($code) && $code >= 400 && $code <= 599
            ? $code
            : 500;
    }
}
