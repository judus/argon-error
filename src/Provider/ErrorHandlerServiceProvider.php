<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Provider;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyRegistryInterface;
use Maduser\Argon\Error\ErrorHandler;
use Maduser\Argon\Error\ExceptionDispatcher;
use Maduser\Argon\Error\ExceptionFormatter;
use Maduser\Argon\Support\Contracts\ErrorHandlerInterface;
use Maduser\Argon\Support\Contracts\ResponseEmitterInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class ErrorHandlerServiceProvider extends AbstractServiceProvider
{
    /**
     * @throws ContainerException
     */
    #[Override]
    public function register(ArgonContainer $container): void
    {
        $container->set(ExceptionFormatter::class, args: [
            'debug' => $container->getParameters()->get('debug', false)
        ]);

        $container->set(ExceptionFormatterInterface::class, ExceptionFormatter::class);

        $container->set(ErrorHandlerInterface::class, ErrorHandler::class, [
            'logger' => LoggerInterface::class,
            'request' => ServerRequestInterface::class,
            'emitter' => ResponseEmitterInterface::class,
        ]);

        $container->set(ExceptionDispatcherInterface::class, ExceptionDispatcher::class);
        $container->set(
            ExceptionPolicyRegistryInterface::class,
            static function (ArgonContainer $container): ExceptionPolicyRegistryInterface {
                $dispatcher = $container->get(ExceptionDispatcherInterface::class);
                assert($dispatcher instanceof ExceptionPolicyRegistryInterface);

                return $dispatcher;
            }
        );
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    #[Override]
    public function boot(ArgonContainer $container): void
    {
        $container->extend(
            ExceptionDispatcherInterface::class,
            function (ExceptionDispatcherInterface $dispatcher) use ($container): ExceptionDispatcherInterface {
                assert($dispatcher instanceof ExceptionPolicyRegistryInterface);

                foreach ($container->getTagged(ExceptionPolicyInterface::class) as $policy) {
                    assert($policy instanceof ExceptionPolicyInterface);
                    $policy->register($dispatcher);
                }

                return $dispatcher;
            }
        );
    }
}
