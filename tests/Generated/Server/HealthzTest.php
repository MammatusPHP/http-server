<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Generated\Server;

use ColinODell\PsrTestLogger\TestLogger;
use Mammatus\Http\Server\Generated\Server\Healthz;
use Mammatus\Vhost\Healthz\HealthCheckVhost;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class HealthzTest extends TestCase
{
    #[Test]
    public function stop(): void
    {
        $vhost  = new HealthCheckVhost();
        $logger = new TestLogger();

        new Healthz($vhost, $logger)->stop();

        self::assertTrue($logger->hasDebugThatContains('Stopping server: '));
        self::assertTrue($logger->hasDebugThatContains('Stopped server: '));
    }
}
