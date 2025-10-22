<?php

declare(strict_types=1);

namespace Maduser\Argon\Error\Provider;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Contracts\ErrorHandling\Http\ExceptionDispatcherInterface;
use Maduser\Argon\Contracts\ErrorHandling\Http\ExceptionFormatterInterface;
use Maduser\Argon\Contracts\ErrorHandling\Http\ErrorHandlerInterface;
use Maduser\Argon\Contracts\ErrorHandling\Http\ErrorHandlerRegistrarInterface;
use Maduser\Argon\Contracts\Http\ResponseEmitterInterface;
use Maduser\Argon\ErrorHandling\Http\ExceptionDispatcher;
use Maduser\Argon\ErrorHandling\Http\ExceptionFormatter;
use Maduser\Argon\ErrorHandling\Http\ErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ArgonErrorHandlerServiceProvider extends AbstractServiceProvider
{
    /**
     * @throws ContainerException
     */
    public function register(ArgonContainer $container): void
    {
        $container->set(ExceptionFormatter::class, args: [
            'debug' => $container->getParameters()->get('debug', false)
        ]);

        $container->set(ExceptionFormatterInterface::class, ExceptionFormatter::class)
            ->tag(['exception.formatter']);

        $container->set(ErrorHandlerInterface::class, ErrorHandler::class, [
            'logger' => LoggerInterface::class,
            'request' => ServerRequestInterface::class,
            'emitter' => ResponseEmitterInterface::class,
        ])
            ->tag(['exception.handler']);

        $container->set(ExceptionDispatcherInterface::class, ExceptionDispatcher::class)
            ->tag(['exception.dispatcher']);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
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
