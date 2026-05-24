<?php

declare(strict_types=1);

namespace Maduser\Argon\Error;

use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyRegistryInterface;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @psalm-type ReporterEntry = array{
 *     class: class-string<Throwable>,
 *     handler: callable(Throwable, ServerRequestInterface): void,
 *     sequence: int
 * }
 * @psalm-type RendererEntry = array{
 *     class: class-string<Throwable>,
 *     handler: callable(Throwable, ServerRequestInterface): ?ResponseInterface,
 *     sequence: int
 * }
 * @psalm-type RendererMatch = array{
 *     distance: int,
 *     sequence: int,
 *     entry: RendererEntry
 * }
 */
final class ExceptionDispatcher implements ExceptionDispatcherInterface, ExceptionPolicyRegistryInterface
{
    /**
     * @var list<ReporterEntry>
     */
    private array $reporters = [];

    /**
     * @var list<RendererEntry>
     */
    private array $renderers = [];

    private int $sequence = 0;

    public function __construct(
        private readonly ExceptionFormatterInterface $formatter,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>> $exceptionClass
     * @param callable(Throwable, ServerRequestInterface): void $reporter
     */
    #[Override]
    public function report(string|array $exceptionClass, callable $reporter): void
    {
        foreach ($this->normalizeExceptionClasses($exceptionClass) as $class) {
            $this->reporters[] = [
                'class' => $class,
                'handler' => $reporter,
                'sequence' => $this->sequence++,
            ];
        }
    }

    /**
     * @param class-string<Throwable>|list<class-string<Throwable>> $exceptionClass
     * @param callable(Throwable, ServerRequestInterface): ?ResponseInterface $renderer
     */
    #[Override]
    public function render(string|array $exceptionClass, callable $renderer): void
    {
        foreach ($this->normalizeExceptionClasses($exceptionClass) as $class) {
            $this->renderers[] = [
                'class' => $class,
                'handler' => $renderer,
                'sequence' => $this->sequence++,
            ];
        }
    }

    #[Override]
    public function dispatch(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $this->reportException($e, $request);

        $response = $this->renderException($e, $request);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        return $this->formatter->format($e, $request);
    }

    private function reportException(Throwable $e, ServerRequestInterface $request): void
    {
        foreach ($this->reporters as $entry) {
            if (!$this->matches($e, $entry['class'])) {
                continue;
            }

            try {
                ($entry['handler'])($e, $request);
            } catch (Throwable $reporterException) {
                $this->logger?->warning('Exception reporter failed', [
                    'exception_class' => $entry['class'],
                    'reporter_exception' => $reporterException,
                    'handled_exception' => $e,
                ]);
            }
        }
    }

    private function renderException(Throwable $e, ServerRequestInterface $request): ?ResponseInterface
    {
        foreach ($this->matchingRenderers($e) as $entry) {
            try {
                $response = ($entry['handler'])($e, $request);
                if ($response instanceof ResponseInterface) {
                    return $response;
                }
            } catch (Throwable $rendererException) {
                $this->logger?->warning('Exception renderer failed', [
                    'exception_class' => $entry['class'],
                    'renderer_exception' => $rendererException,
                    'handled_exception' => $e,
                ]);
            }
        }

        return null;
    }

    /**
     * @return list<RendererEntry>
     */
    private function matchingRenderers(Throwable $e): array
    {
        $matches = [];

        foreach ($this->renderers as $entry) {
            if ($this->matches($e, $entry['class'])) {
                $matches[] = [
                    'distance' => $this->specificity($e, $entry['class']),
                    'sequence' => $entry['sequence'],
                    'entry' => $entry,
                ];
            }
        }

        /**
         * @param RendererMatch $a
         * @param RendererMatch $b
         */
        $sort = static fn(array $a, array $b): int => $a['distance']
                <=> $b['distance']
                ?: $a['sequence'] <=> $b['sequence'];
        usort(
            $matches,
            $sort
        );

        $renderers = [];
        foreach ($matches as $match) {
            $renderers[] = $match['entry'];
        }

        return $renderers;
    }

    /**
     * @return list<class-string<Throwable>>
     */
    private function normalizeExceptionClasses(string|array $exceptionClass): array
    {
        $rawClasses = is_array($exceptionClass) ? $exceptionClass : [$exceptionClass];
        $classes = [];

        foreach ($rawClasses as $class) {
            if (!is_string($class) || !is_a($class, Throwable::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Exception policy class must be a Throwable class or interface, "%s" given.',
                    is_scalar($class) ? (string) $class : get_debug_type($class)
                ));
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param class-string<Throwable> $class
     */
    private function matches(Throwable $e, string $class): bool
    {
        return $e instanceof $class;
    }

    /**
     * @param class-string<Throwable> $class
     */
    private function specificity(Throwable $e, string $class): int
    {
        if ($e::class === $class) {
            return 0;
        }

        if (class_exists($class)) {
            return $this->classDistance($e::class, $class);
        }

        return 1000;
    }

    /**
     * @param class-string<Throwable> $actual
     * @param class-string<Throwable> $target
     */
    private function classDistance(string $actual, string $target): int
    {
        $distance = 0;
        $current = $actual;

        while ($current !== $target) {
            $parent = get_parent_class($current);
            if ($parent === false) {
                return 1000;
            }

            $current = $parent;
            ++$distance;
        }

        return $distance;
    }
}
