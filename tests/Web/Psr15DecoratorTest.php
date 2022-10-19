<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Web;

use Mammatus\Http\Server\Web\Psr15Decorator;
use Middlewares\ClientIp;
use Psr\Http\Server\MiddlewareInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

final class Psr15DecoratorTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function type(): void
    {
        foreach (
            Psr15Decorator::decorate(
                new ClientIp(),
                static fn (): bool => true,
            ) as $middleware
        ) {
            self::assertNotInstanceOf(MiddlewareInterface::class, $middleware);
        }
    }
}
