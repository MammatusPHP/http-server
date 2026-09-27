<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\DevApp\Http\Server\FrontendVhost;
use Mammatus\Http\Server\WebSocket\Defaults;
use Mammatus\Http\Server\WebSocket\VhostConfiguration;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithDisabledHeartbeat;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithOnlyHeartbeatInterval;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithoutWebSocketAttribute;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithWebSocketAttribute;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class VhostConfigurationTest extends TestCase
{
    #[Test]
    public function defaultsWhenAttributeMissing(): void
    {
        $configuration = VhostConfiguration::fromVhostClass(VhostWithoutWebSocketAttribute::class);

        self::assertSame(Defaults::HEARTBEAT_INTERVAL_SECONDS, $configuration->heartbeatIntervalSeconds);
        self::assertSame(Defaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
        self::assertFalse($configuration->serveClientAsset);
    }

    #[Test]
    public function readsAttributeValues(): void
    {
        $configuration = VhostConfiguration::fromVhostClass(VhostWithWebSocketAttribute::class);

        self::assertSame(9.0, $configuration->heartbeatIntervalSeconds);
        self::assertSame('custom-heartbeat', $configuration->heartbeatChannel);
        self::assertTrue($configuration->serveClientAsset);
    }

    #[Test]
    public function disableHeartbeatViaAttribute(): void
    {
        $configuration = VhostConfiguration::fromVhostClass(VhostWithDisabledHeartbeat::class);

        self::assertNull($configuration->heartbeatIntervalSeconds);
        self::assertSame(Defaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
    }

    #[Test]
    public function devAppFrontendVhostServesClientAsset(): void
    {
        $configuration = VhostConfiguration::fromVhostClass(FrontendVhost::class);

        self::assertTrue($configuration->serveClientAsset);
        self::assertSame(Defaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
    }

    #[Test]
    public function usesDefaultsForMissingAttributesIndependently(): void
    {
        $configuration = VhostConfiguration::fromVhostClass(VhostWithOnlyHeartbeatInterval::class);

        self::assertSame(5.0, $configuration->heartbeatIntervalSeconds);
        self::assertSame(Defaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
        self::assertFalse($configuration->serveClientAsset);
    }
}
