<?php

declare(strict_types=1);

namespace Tests\Unit;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Error\Provider\ErrorHandlerServiceProvider;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerServiceProviderTest extends TestCase
{
    public function testProviderIsAnArgonServiceProvider(): void
    {
        self::assertTrue(is_subclass_of(ErrorHandlerServiceProvider::class, AbstractServiceProvider::class));
    }
}
