<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Provider;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Error\Contracts\ErrorHandlerRegistrarInterface;
use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Maduser\Argon\Error\Contracts\ExceptionFormatterInterface;
use Maduser\Argon\Error\ErrorHandler;
use Maduser\Argon\Error\ExceptionDispatcher;
use Maduser\Argon\Error\ExceptionFormatter;
use Maduser\Argon\Support\Contracts\ErrorHandlerInterface;
use Maduser\Argon\Support\Contracts\ResponseEmitterInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ErrorHandlerServiceProvider extends AbstractServiceProvider
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
                foreach ($container->getTagged(ErrorHandlerRegistrarInterface::class) as $registrar) {
                    assert($registrar instanceof ErrorHandlerRegistrarInterface);
                    $registrar->register($dispatcher);
                }

                return $dispatcher;
            }
        );
    }
}
