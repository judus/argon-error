# Argon Error

[![PHP](https://img.shields.io/badge/php-8.2+-blue)](https://www.php.net/)
[![Build](https://github.com/judus/argon-error/actions/workflows/php.yml/badge.svg)](https://github.com/judus/argon-error/actions)
[![codecov](https://codecov.io/gh/judus/argon-error/branch/master/graph/badge.svg)](https://codecov.io/gh/judus/argon-error)
[![Psalm Level](https://shepherd.dev/github/judus/argon-error/coverage.svg)](https://shepherd.dev/github/judus/argon-error)
[![Latest Version](https://img.shields.io/packagist/v/maduser/argon-error.svg)](https://packagist.org/packages/maduser/argon-error)
[![Downloads](https://img.shields.io/packagist/dt/maduser/argon-error.svg)](https://packagist.org/packages/maduser/argon-error)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

`maduser/argon-error` is the HTTP error-handling layer for Argon applications.
It turns uncaught throwables into PSR-7 responses, lets applications register
exception-specific reporting and rendering policies, and integrates with the
shared Argon runtime error-handler contract.

The package stays intentionally small:

- `ErrorHandler` bridges Argon runtime failures to the dispatcher/formatter
  stack.
- `ExceptionDispatcher` runs registered exception policies before falling back to
  the formatter.
- `ExceptionFormatter` creates JSON or plain-text PSR-7 responses.
- `ErrorHandlerServiceProvider` wires the package into an `ArgonContainer`.

## Installation

```bash
composer require maduser/argon-error
```

The formatter depends on PSR-17 response and stream factories. In a full Argon
stack those are normally provided by `maduser/argon-http-message`.

## Service Provider

Register the provider during application boot:

```php
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Error\Provider\ErrorHandlerServiceProvider;

$container = new ArgonContainer();
$container->register(ErrorHandlerServiceProvider::class);
$container->boot();
```

The provider binds:

- `Maduser\Argon\Support\Contracts\ErrorHandlerInterface`
- `Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface`
- `Maduser\Argon\Error\Contracts\ExceptionFormatterInterface`
- `Maduser\Argon\Error\Contracts\ExceptionPolicyRegistryInterface`

Any service tagged as `ExceptionPolicyInterface` can register custom exception
reporting and rendering during container boot.

```php
use App\Http\AppExceptionPolicy;
use Maduser\Argon\Error\Contracts\ExceptionPolicyInterface;

$container->set(AppExceptionPolicy::class)->tag(ExceptionPolicyInterface::class);
```

## Exception Policies

Policies separate side effects from response creation:

- reporters run first for all matching exception types;
- renderers run after reporters and may return a `ResponseInterface`;
- a renderer returning `null` lets the dispatcher continue;
- if no renderer returns a response, the formatter creates the fallback response.

```php
use Maduser\Argon\Error\Contracts\ExceptionPolicyInterface;
use Maduser\Argon\Error\Contracts\ExceptionPolicyRegistryInterface;
use App\Exceptions\PaymentFailed;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final readonly class AppExceptionPolicy implements ExceptionPolicyInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {
    }

    public function register(ExceptionPolicyRegistryInterface $exceptions): void
    {
        $exceptions->report(
            Throwable::class,
            fn(Throwable $e, ServerRequestInterface $request): void => $this->logger->error(
                $e->getMessage(),
                ['exception' => $e, 'path' => $request->getUri()->getPath()]
            )
        );

        $exceptions->report(
            PaymentFailed::class,
            fn(PaymentFailed $e): void => $this->notifyBillingChannel($e)
        );

        $exceptions->render(
            RuntimeException::class,
            fn(RuntimeException $e): ?ResponseInterface => $this->responses
                ->createResponse(500)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streams->createStream('{"error":"Runtime failure"}'))
        );
    }
}
```

Renderer selection is deterministic. More specific exception classes win before
parent classes or interfaces. If two renderers are registered for the same
specificity, registration order wins.

Reporter and renderer failures are logged and swallowed. Exception handling must
not fail because an application callback failed.

## Formatting

`ExceptionFormatter` uses the request `Accept` header:

- `application/json` returns a JSON error payload.
- anything else returns `text/plain`.

HTTP status resolution is deliberately conservative:

- exceptions implementing `HttpExceptionInterface` may provide an explicit
  status code;
- otherwise throwable codes in the `400..599` range are used;
- invalid or non-HTTP codes fall back to `500`.

Stack traces are hidden by default. They are included only when debug mode is
enabled or the exception implements `SafeToDisplayExceptionInterface`.
