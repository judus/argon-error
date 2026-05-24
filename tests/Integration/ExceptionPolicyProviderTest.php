<?php

declare(strict_types=1);

namespace Tests\Integration;

use ArrayObject;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyRegistryInterface;
use Maduser\Argon\Error\Provider\ErrorHandlerServiceProvider;
use Override;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use Tests\Support\ResponseFactoryStub;
use Tests\Support\ResponseStub;
use Tests\Support\StreamFactoryStub;
use Throwable;

final class ExceptionPolicyProviderTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testTaggedPoliciesAreAppliedDuringContainerBoot(): void
    {
        /** @var ArrayObject<int, string> $events */
        $events = new ArrayObject();
        $response = new ResponseStub(409);
        $container = $this->createContainer();

        $container->set(
            'test.exception.policy',
            static fn(): ExceptionPolicyInterface => new class (
                $events,
                $response
            ) implements ExceptionPolicyInterface {
                /**
                 * @param ArrayObject<int, string> $events
                 */
                public function __construct(
                    private readonly ArrayObject $events,
                    private readonly ResponseInterface $response
                ) {
                }

                #[Override]
                public function register(ExceptionPolicyRegistryInterface $exceptions): void
                {
                    $exceptions->report(Throwable::class, function (): void {
                        $this->events->append('reported');
                    });
                    $exceptions->render(RuntimeException::class, fn(): ResponseInterface => $this->response);
                }
            }
        )->tag(ExceptionPolicyInterface::class);

        $container->boot();

        $dispatcher = $container->get(ExceptionDispatcherInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        self::assertSame($response, $dispatcher->dispatch(new RuntimeException('Handled'), $request));
        self::assertSame(['reported'], $events->getArrayCopy());
    }

    /**
     * @throws Exception
     */
    public function testPolicyRegistryResolvesToConfiguredDispatcher(): void
    {
        $response = new ResponseStub(204);
        $container = $this->createContainer();

        $container->set(
            'test.exception.policy',
            static fn(): ExceptionPolicyInterface => new class ($response) implements ExceptionPolicyInterface {
                public function __construct(private readonly ResponseInterface $response)
                {
                }

                #[Override]
                public function register(ExceptionPolicyRegistryInterface $exceptions): void
                {
                    $exceptions->render(RuntimeException::class, fn(): ResponseInterface => $this->response);
                }
            }
        )->tag(ExceptionPolicyInterface::class);

        $container->boot();

        $registry = $container->get(ExceptionPolicyRegistryInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);

        self::assertInstanceOf(ExceptionDispatcherInterface::class, $registry);
        self::assertSame($response, $registry->dispatch(new RuntimeException('Handled'), $request));
    }

    /**
     * @throws Exception
     */
    private function createContainer(): ArgonContainer
    {
        $container = new ArgonContainer();
        $container->set(ResponseFactoryInterface::class, ResponseFactoryStub::class);
        $container->set(StreamFactoryInterface::class, StreamFactoryStub::class);
        $container->register(ErrorHandlerServiceProvider::class);

        return $container;
    }
}
