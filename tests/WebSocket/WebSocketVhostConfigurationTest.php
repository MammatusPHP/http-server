<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\DevApp\Http\Server\FrontendVhost;
use Mammatus\Http\Server\WebSocket\WebSocketDefaults;
use Mammatus\Http\Server\WebSocket\WebSocketVhostConfiguration;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithDisabledHeartbeat;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithOnlyHeartbeatInterval;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithoutWebSocketAttribute;
use Mammatus\Tests\Http\Server\WebSocket\Fixtures\VhostWithWebSocketAttribute;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class WebSocketVhostConfigurationTest extends TestCase
{
    #[Test]
    public function defaultsWhenAttributeMissing(): void
    {
        $configuration = WebSocketVhostConfiguration::fromVhostClass(VhostWithoutWebSocketAttribute::class);

        self::assertSame(WebSocketDefaults::HEARTBEAT_INTERVAL_SECONDS, $configuration->heartbeatIntervalSeconds);
        self::assertSame(WebSocketDefaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
        self::assertFalse($configuration->serveClientAsset);
    }

    #[Test]
    public function readsAttributeValues(): void
    {
        $configuration = WebSocketVhostConfiguration::fromVhostClass(VhostWithWebSocketAttribute::class);

        self::assertSame(9.0, $configuration->heartbeatIntervalSeconds);
        self::assertSame('custom-heartbeat', $configuration->heartbeatChannel);
        self::assertTrue($configuration->serveClientAsset);
    }

    #[Test]
    public function disableHeartbeatViaAttribute(): void
    {
        $configuration = WebSocketVhostConfiguration::fromVhostClass(VhostWithDisabledHeartbeat::class);

        self::assertNull($configuration->heartbeatIntervalSeconds);
        self::assertSame(WebSocketDefaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
    }

    #[Test]
    public function devAppFrontendVhostServesClientAsset(): void
    {
        $configuration = WebSocketVhostConfiguration::fromVhostClass(FrontendVhost::class);

        self::assertTrue($configuration->serveClientAsset);
        self::assertSame(WebSocketDefaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
    }

    #[Test]
    public function usesDefaultsForMissingAttributesIndependently(): void
    {
        $configuration = WebSocketVhostConfiguration::fromVhostClass(VhostWithOnlyHeartbeatInterval::class);

        self::assertSame(5.0, $configuration->heartbeatIntervalSeconds);
        self::assertSame(WebSocketDefaults::HEARTBEAT_CHANNEL, $configuration->heartbeatChannel);
        self::assertFalse($configuration->serveClientAsset);
    }
}
