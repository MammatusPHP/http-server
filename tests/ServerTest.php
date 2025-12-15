<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server;

use Mammatus\Http\Server\Generated\Server\Healthz;
use Mammatus\Http\Server\Server;
use Mammatus\LifeCycleEvents\Shutdown;
use Mammatus\LifeCycleEvents\Start;
use PHPUnit\Framework\Attributes\Test;
use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use WyriHaximus\TestUtilities\TestCase;

final class ServerTest extends TestCase
{
    #[Test]
    public function servers(): void
    {
        self::assertSame(
            [
                Healthz::NAME => Healthz::class,
            ],
            [
                ...new Server(new PsrContainer(new Container()))->servers(),
            ],
        );
    }

    #[Test]
    public function lifeCycle(): void
    {
        $mockVhost = new MockVhost();
        $server    = new Server(new PsrContainer(new Container([Healthz::class => $mockVhost])));

        self::assertFalse($mockVhost->started);
        self::assertFalse($mockVhost->stopped);

        $server->start(new Start());

        self::assertTrue($mockVhost->started);
        self::assertFalse($mockVhost->stopped);

        $server->stop(new Shutdown());

        self::assertTrue($mockVhost->started);
        self::assertTrue($mockVhost->stopped);
    }
}
