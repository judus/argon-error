# Argon Error

`maduser/argon-error` is the HTTP error-handling layer for Argon applications.
It turns uncaught throwables into PSR-7 responses, lets applications register
exception-specific responders, and integrates with the shared Argon runtime
error-handler contract.

The package stays intentionally small:

- `ErrorHandler` bridges Argon runtime failures to the dispatcher/formatter
  stack.
- `ExceptionDispatcher` runs registered exception responders before falling back
  to the formatter.
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

Any service tagged as `ErrorHandlerRegistrarInterface` can register custom
exception responders during container boot.

## Exception Responders

Responders receive the thrown exception and the active server request. Returning
a `ResponseInterface` stops dispatch. Returning `null` lets the dispatcher keep
looking and eventually fall back to the formatter.

```php
use Maduser\Argon\Error\Contracts\ExceptionDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

$dispatcher->register(
    RuntimeException::class,
    static function (Throwable $exception, ServerRequestInterface $request): ?ResponseInterface {
        // Return a PSR-7 response, or null to let the formatter handle it.
        return null;
    }
);
```

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
