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
    public function fullCycle(): void
    {
        $vhost  = new HealthCheckVhost();
        $logger = new TestLogger();

        $server = new Healthz($vhost, $logger);
        $server->start();
        $server->start();
        $server->stop();

        self::assertTrue($logger->hasDebugThatContains('Starting server: '));
        self::assertTrue($logger->hasDebugThatContains('Started server: '));
        self::assertTrue($logger->hasWarningThatContains('Server already started: '));
        self::assertTrue($logger->hasDebugThatContains('Stopping server: '));
        self::assertTrue($logger->hasDebugThatContains('Stopped server: '));
    }
}
